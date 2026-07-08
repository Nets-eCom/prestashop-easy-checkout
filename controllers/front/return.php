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
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @property Nexi_Checkout $module
 */
class Nexi_CheckoutReturnModuleFrontController extends ModuleFrontController
{
    private LoggerInterface $logger;
    private PaymentApiFactory $paymentApiFactory;
    private ConfigurationProvider $configurationProvider;
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;

    public function init(): void
    {
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

        /** @var LoggerInterface $logger */
        $logger = $this->get('nexi_checkout.logger');
        $this->logger = $logger;

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->get('nexi_checkout.dispatcher');
        $this->eventDispatcher = $dispatcher;
    }

    public function postProcess(): void
    {
        $paymentId = Tools::getValue('paymentid') ?: Tools::getValue('paymentId', '');
        $api = $this->createPaymentApi();

        if ($paymentId === '') {
            $this->logger->error('Return: Missing paymentId parameter');
            $this->redirectToOrderPage();

            return;
        }

        try {
            $paymentResult = $api->retrievePayment($paymentId);
        } catch (PaymentApiException $e) {
            $this->logger->error('Return: Couldn\'t retrieve payment', [
                'paymentId' => $paymentId,
                'exception' => $e->getMessage(),
            ]);

            $this->redirectToOrderPageWithError('An error occurred while processing your payment.');

            return;
        }

        $payment = $paymentResult->getPayment();

        $cartId = (int) $payment->getOrderDetails()->getReference();
        $cart = new Cart($cartId);

        if (!Validate::isLoadedObject($cart)) {
            $this->logger->error('Return: Invalid cart', ['cartId' => $cartId, 'paymentId' => $paymentId]);
            $this->redirectToOrderPageWithError('Your cart could not be found. Please try again.');

            return;
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            $this->logger->error('Return: Invalid customer', ['cartId' => $cartId, 'paymentId' => $paymentId]);
            $this->redirectToOrderPageWithError('Customer information could not be verified. Please try again.');

            return;
        }

        $myReference = $payment->getMyReference();

        if ($cart->secure_key !== $myReference) {
            $this->logger->error('Return: Invalid cart secure_key', ['cartId' => $cartId, 'paymentId' => $paymentId]);
            $this->redirectToOrderPageWithError('Something went wrong. Please try again.');

            return;
        }

        if ($cart->orderExists()) {
            $orderId = Order::getIdByCartId($cartId);
            $this->logger->info('Return: Order already exists', ['orderId' => $orderId, 'paymentId' => $paymentId]);
            $this->redirectToConfirmation($cart, $orderId, $payment->getMyReference());

            return;
        }

        $newOrderId = $this->createOrder($cart, $customer, $payment);

        $this->eventDispatcher->dispatch(new OrderCreatedEvent($newOrderId));

        $this->redirectToConfirmation($cart, $newOrderId, $cart->secure_key);
    }

    private function createOrder(
        Cart $cart,
        Customer $customer,
        Payment $payment,
    ): int {
        $currency = new Currency($cart->id_currency);
        $paymentId = $payment->getPaymentId();
        $total = (float) $payment->getOrderDetails()->getAmount() / 100;

        $paymentMethod = $this->getPaymentMethodName($payment);

        $orderState = match ($payment->getStatus()) {
            PaymentStatusEnum::RESERVED => Configuration::get(OrderStateDictionary::PAYMENT_ACCEPTED),
            PaymentStatusEnum::CHARGED => Configuration::get('PS_OS_WS_PAYMENT'),
            default => Configuration::get(OrderStateDictionary::PAYMENT_NEW),
        };

        $this->module->validateOrder(
            (int) $cart->id,
            (int) $orderState,
            $total,
            $paymentMethod,
            null,
            ['transaction_id' => $paymentId],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $orderId = (int) $this->module->currentOrder;

        if ($orderId === 0) {
            $this->logger->error('Return: Failed to create order', [
                'paymentId' => $paymentId,
                'cartId' => $cart->id,
            ]);

            $this->redirectToOrderPageWithError('Failed to create order.');
        }

        $this->storePaymentDetails($orderId, $paymentId);

        $this->logger->info('Return: Order created successfully', [
            'orderId' => $orderId,
            'paymentId' => $paymentId,
            'cartId' => $cart->id,
        ]);

        return $orderId;
    }

    private function getPaymentMethodName(Payment $payment): string
    {
        $paymentDetails = $payment->getPaymentDetails();

        if ($paymentDetails !== null) {
            $paymentType = $paymentDetails->getPaymentType();
            $paymentMethod = $paymentDetails->getPaymentMethod();

            return sprintf('Nexi Checkout - %s', ucfirst($paymentMethod ?? $paymentType->value));
        }

        return $this->module->displayName;
    }

    private function storePaymentDetails(int $orderId, string $paymentId): void
    {
        /** @var PaymentDetailsRepository $paymentDetailsRepository */
        $paymentDetailsRepository = $this->entityManager->getRepository(NexiCheckoutPaymentDetails::class);
        /** @var NexiCheckoutPaymentDetails $paymentDetails */
        $paymentDetails = $paymentDetailsRepository->findOneByPaymentId($paymentId);
        if (!empty($paymentDetails->getOrderId()) && $paymentDetails->getOrderId() !== $orderId) {
            $this->logger->error('Return: PaymentDetails assigned to different order', [
                'paymentId' => $paymentId,
                'paymentDetails' => [
                    'id' => $paymentDetails->getId(),
                    'orderId' => $paymentDetails->getOrderId(),
                ],
                'orderId' => $orderId,
            ]);

            return;
        }

        $paymentDetails->setOrderId($orderId);

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

    private function redirectToConfirmation(Cart $cart, int $orderId, string $secureKey): void
    {
        Tools::redirect(
            $this->context->link->getPageLink(
                'order-confirmation',
                true,
                null,
                [
                    'id_cart' => (int) $cart->id,
                    'id_module' => (int) $this->module->id,
                    'id_order' => $orderId,
                    'key' => $secureKey,
                ]
            )
        );
    }

    private function redirectToOrderPage(): void
    {
        Tools::redirect($this->context->link->getPageLink('order', true));
    }

    private function redirectToOrderPageWithError(string $message): void
    {
        $this->errors[] = $this->trans($message, [], 'Modules.Nexicheckout.PaymentError');
        $this->redirectWithNotifications($this->context->link->getPageLink('order', true));
    }
}
