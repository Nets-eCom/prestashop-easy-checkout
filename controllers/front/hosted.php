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
use Nexi\Checkout\Helper\LanguageProvider;
use Nexi\Checkout\RequestBuilder\PaymentRequest;
use Nexi\Checkout\Traits\CartValidationTrait;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\Exception\UnauthorizedApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Request\Payment;
use NexiCheckout\Model\Request\Payment\MethodConfiguration;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}
class Nexi_CheckoutHostedModuleFrontController extends ModuleFrontController
{
    use CartValidationTrait;

    private ConfigurationProvider $configurationProvider;
    private PaymentApiFactory $paymentApiFactory;
    private PaymentRequest $paymentRequest;
    private EntityManagerInterface $entityManager;
    private LanguageProvider $languageProvider;
    private LoggerInterface $logger;

    public function init(): void
    {
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

        /** @var LanguageProvider $languageProvider */
        $languageProvider = $this->get(LanguageProvider::class);
        $this->languageProvider = $languageProvider;

        /** @var LoggerInterface $logger */
        $logger = $this->get('nexi_checkout.logger');
        $this->logger = $logger;
    }

    public function postProcess(): void
    {
        $cart = $this->context->cart;

        if (!$this->canProcess($cart)) {
            $this->errors[] = $this->trans('Cart is not valid', [], 'Modules.Nexicheckout.PaymentError');
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true));

            return;
        }

        $api = $this->createPaymentApi();
        $paymentRequest = $this->paymentRequest->buildHosted($cart, $this->buildMethodConfiguration());

        try {
            $hostedPaymentResult = $api->createHostedPayment($paymentRequest);
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Hosted payment create error', [
                'request' => $paymentRequest,
                'exception' => $paymentApiException,
            ]);

            if ($paymentApiException instanceof UnauthorizedApiException) {
                $this->errors[] = $this->trans('Wrong payment configuration - contact with shop administrator!', [], 'Modules.Nexicheckout.PaymentError');
            } else {
                $this->errors[] = $this->trans('Unable to initialize payment. Please try again or choose another payment method.', [], 'Modules.Nexicheckout.PaymentError');
            }

            $this->redirectWithNotifications($this->context->link->getPageLink('order', true));

            return;
        }

        $this->createPaymentDetails($hostedPaymentResult->getPaymentId(), $paymentRequest);

        Tools::redirect($this->createRedirectUrl($hostedPaymentResult->getHostedPaymentPageUrl()));
    }

    private function createPaymentApi(): PaymentApi
    {
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);

        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($shopConstraint),
            $this->configurationProvider->isLiveMode($shopConstraint),
        );
    }

    private function createPaymentDetails(string $paymentId, Payment $paymentRequest): void
    {
        $order = $paymentRequest->getOrder();

        $paymentDetails = new NexiCheckoutPaymentDetails();
        $paymentDetails->setPaymentId($paymentId);
        $paymentDetails->setOrderData($order);
        $paymentDetails->setOrderReference($order->getReference());

        $this->entityManager->persist($paymentDetails);
        $this->entityManager->flush();
    }

    private function canProcess(Cart $cart): bool
    {
        return $this->isCartReadyForPayment($cart) && $this->module->active;
    }

    /**
     * @return list<MethodConfiguration>
     */
    private function buildMethodConfiguration(): array
    {
        $method = Tools::getValue('method');

        if (!$method || !is_string($method)) {
            return [];
        }

        return array_map(
            fn (string $name): MethodConfiguration => new MethodConfiguration($name, true),
            PaymentRequest::resolveMethodNames($method)
        );
    }

    private function createRedirectUrl(
        string $hostedPaymentPageUrl,
    ): string {
        return \sprintf(
            '%s&language=%s',
            $hostedPaymentPageUrl,
            $this->languageProvider->provide($this->context->language->iso_code)
        );
    }
}
