<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Helper\CartSignature;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Nexi\Checkout\RequestBuilder\PaymentRequest;
use Nexi\Checkout\Traits\CartValidationTrait;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Request\Payment\MethodConfiguration;
use NexiCheckout\Model\Request\Shared\Order;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Nexi_CheckoutCreate_embedded_paymentModuleFrontController extends ModuleFrontController
{
    use CartValidationTrait;

    private ConfigurationProvider $configurationProvider;
    private PaymentApiFactory $paymentApiFactory;
    private PaymentRequest $paymentRequest;
    private EntityManagerInterface $entityManager;
    private PaymentDetailsRepository $paymentDetailsRepository;
    private CartSignature $cartSignature;
    private LoggerInterface $logger;

    public function init(): void
    {
        $this->ajax = true;

        parent::init();

        /** @var ConfigurationProvider $configProvider */
        $configProvider = $this->get(ConfigurationProvider::class);
        $this->configurationProvider = $configProvider;

        /** @var PaymentApiFactory $apiFactory */
        $apiFactory = $this->get(PaymentApiFactory::class);
        $this->paymentApiFactory = $apiFactory;

        /** @var PaymentRequest $requestBuilder */
        $requestBuilder = $this->get(PaymentRequest::class);
        $this->paymentRequest = $requestBuilder;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var PaymentDetailsRepository $paymentDetailsRepository */
        $paymentDetailsRepository = $this->get(PaymentDetailsRepository::class);
        $this->paymentDetailsRepository = $paymentDetailsRepository;

        /** @var CartSignature $cartSignature */
        $cartSignature = $this->get(CartSignature::class);
        $this->cartSignature = $cartSignature;

        /** @var LoggerInterface $logger */
        $logger = $this->get('nexi_checkout.logger');
        $this->logger = $logger;
    }

    public function postProcess(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        $method = $data['method'] ?? null;

        if (!$method || !is_string($method)) {
            $this->returnError($this->getTranslator()->trans('Something went wrong. Please try again.', [], 'Modules.Nexicheckout.Payment'));

            return;
        }

        $cart = $this->context->cart;

        if (!$this->isCartReadyForPayment($cart) || !$this->module->active) {
            $this->returnError($this->getTranslator()->trans('Something went wrong. Please try again.', [], 'Modules.Nexicheckout.Payment'));

            return;
        }

        $shopConstraint = ShopConstraint::shop($this->context->shop->id);
        $api = $this->createPaymentApi($shopConstraint);
        $paymentRequest = $this->paymentRequest->buildEmbedded($cart, $this->buildMethodConfiguration($method));

        try {
            $result = $api->createEmbeddedPayment($paymentRequest);
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('CreateEmbeddedPayment: Failed to create payment for split method', [
                'method' => $method,
                'exception' => $paymentApiException->getMessage(),
                'request' => $paymentRequest,
            ]);
            $this->returnError($this->getTranslator()->trans('Unable to initialize payment. Please try again or choose another payment method.', [], 'Modules.Nexicheckout.Payment'));

            return;
        }

        $paymentId = $result->getPaymentId();
        $cartSignature = $this->cartSignature->generateHashSignatureByCart($cart);
        $this->storePaymentDetails($paymentRequest->getOrder(), $paymentId, $cartSignature);

        $this->ajaxRender(json_encode([
            'paymentId' => $paymentId,
        ]));
    }

    /**
     * @return list<MethodConfiguration>
     */
    private function buildMethodConfiguration(string $methodName): array
    {
        return array_map(
            fn (string $name): MethodConfiguration => new MethodConfiguration($name, true),
            PaymentRequest::resolveMethodNames($methodName)
        );
    }

    private function createPaymentApi(ShopConstraint $shopConstraint): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($shopConstraint),
            $this->configurationProvider->isLiveMode($shopConstraint),
        );
    }

    private function storePaymentDetails(Order $order, string $paymentId, string $cartSignature): void
    {
        $cartReference = $order->getReference();
        $details = $this->paymentDetailsRepository->findOneByOrderReference($cartReference);

        if (!$details instanceof NexiCheckoutPaymentDetails) {
            $details = new NexiCheckoutPaymentDetails();
            $details->setOrderReference($cartReference);
        }

        $details->setPaymentId($paymentId);
        $details->setEmbeddedCartHash($cartSignature);
        $details->setOrderData($order);

        $this->entityManager->persist($details);
        $this->entityManager->flush();
    }

    private function returnError(string $message): void
    {
        http_response_code(400);
        $this->ajaxRender(json_encode(['error' => $message]));
    }
}
