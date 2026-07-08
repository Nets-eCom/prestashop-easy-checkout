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
use Nexi\Checkout\Configuration\OrderStateDictionary;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Event\OrderCreatedEvent;
use Nexi\Checkout\Helper\CartSignature;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}
/**
 * @property Nexi_Checkout $module
 */
class Nexi_CheckoutEmbedded_create_orderModuleFrontController extends ModuleFrontController
{
    private PaymentApiFactory $paymentApiFactory;
    private ConfigurationProvider $configurationProvider;
    private EntityManagerInterface $entityManager;
    private PaymentDetailsRepository $paymentDetailsRepository;
    private CartSignature $cartSignature;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;

    public function init(): void
    {
        $this->ajax = true;

        parent::init();

        /** @var PaymentApiFactory $apiFactory */
        $apiFactory = $this->get(PaymentApiFactory::class);
        $this->paymentApiFactory = $apiFactory;

        /** @var ConfigurationProvider $configProvider */
        $configProvider = $this->get(ConfigurationProvider::class);
        $this->configurationProvider = $configProvider;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var PaymentDetailsRepository $paymentDetailsRepository */
        $paymentDetailsRepository = $this->get(PaymentDetailsRepository::class);
        $this->paymentDetailsRepository = $paymentDetailsRepository;

        /** @var CartSignature $cartSignature */
        $cartSignature = $this->get(CartSignature::class);
        $this->cartSignature = $cartSignature;

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->get('nexi_checkout.dispatcher');
        $this->eventDispatcher = $dispatcher;

        /** @var LoggerInterface $logger */
        $logger = $this->get('nexi_checkout.logger');
        $this->logger = $logger;
    }

    // @todo render error ine the FO
    public function postProcess(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $paymentId = $data['paymentId'] ?? null;

        if (!$paymentId) {
            $this->returnError('Payment ID is missing');

            return;
        }

        $api = $this->createPaymentApi();

        try {
            $paymentResult = $api->retrievePayment($paymentId);
        } catch (PaymentApiException $e) {
            $this->logger->error('EmbeddedCreateOrder: Couldn\'t retrieve payment', [
                'paymentId' => $paymentId,
                'exception' => $e->getMessage(),
            ]);

            $this->returnError('An error occurred while processing your payment.');

            return;
        }

        $payment = $paymentResult->getPayment();
        $cartId = (int) $payment->getOrderDetails()->getReference();
        $cart = new Cart($cartId);

        if (!Validate::isLoadedObject($cart)) {
            $this->logger->error('EmbeddedCreateOrder: Invalid cart', ['cartId' => $cartId, 'paymentId' => $paymentId]);
            $this->returnError(
                $this->getTranslator()->trans('Your cart could not be found. Please try again.', [], 'Modules.Nexicheckout.Payment')
            );

            return;
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            $this->logger->error('EmbeddedCreateOrder: Invalid customer', ['cartId' => $cartId, 'paymentId' => $paymentId]);
            $this->returnError(
                $this->getTranslator()->trans('Customer information could not be verified. Please try again.', [], 'Modules.Nexicheckout.Payment')
            );

            return;
        }

        if ($cart->secure_key !== $payment->getMyReference()) {
            $this->logger->error('EmbeddedCreateOrder: Invalid cart secure_key', ['cartId' => $cartId, 'paymentId' => $paymentId]);
            $this->returnError(
                $this->getTranslator()->trans('Something went wrong. Please try again.', [], 'Modules.Nexicheckout.Payment')
            );

            return;
        }

        if ($cart->orderExists()) {
            $orderId = Order::getIdByCartId($cartId);
            $this->logger->info('EmbeddedCreateOrder: Order already exists', ['orderId' => $orderId, 'paymentId' => $paymentId]);
            $this->returnErrorWithRedirect(
                $this->getTranslator()->trans('Order already exists', [], 'Modules.Nexicheckout.Payment'),
                $this->getConfirmationUrl($cart, $orderId)
            );

            return;
        }

        if (!$this->isPaymentDataSame($cart, $paymentId)) {
            $this->logger->error('Return: Cart data does not match payment data.', ['cartId' => $cartId, 'paymentId' => $paymentId]);
            $this->returnError(
                $this->getTranslator()->trans('Cart was modified. Please reselect your payment method.', [], 'Modules.Nexicheckout.Payment')
            );

            return;
        }

        $orderId = $this->createOrder($cart, $customer, $payment);

        $this->eventDispatcher->dispatch(new OrderCreatedEvent($orderId));

        $this->returnSuccess($this->getConfirmationUrl($cart, $orderId));
    }

    private function createOrder(Cart $cart, Customer $customer, Payment $payment): int
    {
        $currency = new Currency($cart->id_currency);
        $paymentId = $payment->getPaymentId();
        $total = (float) $payment->getOrderDetails()->getAmount() / 100;

        $this->module->validateOrder(
            (int) $cart->id,
            (int) Configuration::get(OrderStateDictionary::PAYMENT_NEW),
            $total,
            'Nexi Checkout',
            null,
            ['transaction_id' => $paymentId],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $orderId = $this->module->currentOrder;

        if ($orderId === 0) {
            $this->logger->error('EmbeddedCreateOrder: Failed to create order', [
                'paymentId' => $paymentId,
                'cartId' => $cart->id,
            ]);

            $this->returnError('Failed to create order.');
        }

        $this->updatePaymentDetails($orderId, $paymentId);

        $this->logger->info('EmbeddedCreateOrder: Order created successfully', [
            'orderId' => $orderId,
            'paymentId' => $paymentId,
            'cartId' => $cart->id,
        ]);

        return $orderId;
    }

    private function updatePaymentDetails(int $orderId, string $paymentId): void
    {
        $paymentDetail = $this->paymentDetailsRepository->findOneByPaymentId($paymentId);

        if (!$paymentDetail instanceof NexiCheckoutPaymentDetails) {
            $this->logger->info('EmbeddedCreateOrder: Couldn\'t find payment details', [
                'orderId' => $orderId,
                'paymentId' => $paymentId,
            ]);

            $this->returnError('Payment not found');
        }

        $paymentDetail->setOrderId($orderId);

        $this->entityManager->persist($paymentDetail);
        $this->entityManager->flush();
    }

    private function createPaymentApi(): PaymentApi
    {
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);

        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($shopConstraint),
            $this->configurationProvider->isLiveMode($shopConstraint),
        );
    }

    private function returnSuccess(string $redirectUrl): void
    {
        $this->ajaxRender(json_encode([
            'redirectUrl' => $redirectUrl,
        ]));
    }

    private function returnErrorWithRedirect(string $message, $redirectUrl): void
    {
        http_response_code(400);
        $this->ajaxRender(json_encode([
            'message' => $message,
            'redirectUrl' => $redirectUrl,
        ]));
    }

    private function returnError(string $message): void
    {
        http_response_code(400);
        $this->ajaxRender(json_encode(['message' => $message]));
    }

    private function isPaymentDataSame(Cart $cart, string $paymentId): bool
    {
        $paymentDetail = $this->paymentDetailsRepository->findOneByPaymentId($paymentId);

        if (!$paymentDetail instanceof NexiCheckoutPaymentDetails) {
            return false;
        }

        $cartSignature = $this->cartSignature->generateHashSignatureByCart($cart);

        return $cartSignature === $paymentDetail->getEmbeddedCartHash();
    }

    private function getConfirmationUrl(Cart $cart, int $orderId): string
    {
        return $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            [
                'id_cart' => (int) $cart->id,
                'id_module' => (int) $this->module->id,
                'id_order' => $orderId,
                'key' => $cart->secure_key,
            ]
        );
    }
}
