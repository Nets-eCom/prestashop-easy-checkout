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

namespace Nexi\Checkout\WebhookProcessor\Processor;

use Nexi\Checkout\Configuration\OrderStateDictionary;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\OrderCreate;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorException;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorInterface;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentDetails;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiCheckout\Model\Webhook\EventNameEnum;
use NexiCheckout\Model\Webhook\WebhookInterface;
use PrestaShop\PrestaShop\Adapter\Order\Repository\OrderRepository;
use PrestaShop\PrestaShop\Core\Domain\Order\ValueObject\OrderId;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class CheckoutCompleted implements WebhookProcessorInterface
{
    use ProcessorLogTrait;

    private const SUPPORTED_PAYMENT_METHODS = ['Swish', 'Sofort', 'Trustly'];

    public function __construct(
        private PaymentDetailsRepository $paymentDetailsRepository,
        private PaymentFetcherInterface $paymentFetcher,
        private OrderRepository $orderRepository,
        private OrderCreate $orderCreate,
        private LoggerInterface $logger,
    ) {
    }

    public function process(WebhookInterface $webhook): void
    {
        $event = $webhook->getEvent();
        $paymentId = $webhook->getData()->getPaymentId();
        $payment = $this->getPayment($paymentId);
        $details = $this->paymentDetailsRepository->findOneByPaymentId($paymentId);
        if (!$details instanceof NexiCheckoutPaymentDetails) {
            throw new WebhookProcessorException(sprintf('Payment details not found for %s.', $paymentId));
        }

        $cartId = $details->getOrderData()['reference'];
        $cart = new \Cart((int) $cartId);

        $orderId = $details->getOrderId();
        if ($orderId === null && !$cart->orderExists()) {
            sleep(5); // give the return action time to create the order first
            $orderId = $this->createOrder($event, $cart, $payment);
        }

        $order = $this->orderRepository->get(new OrderId($orderId));

        $this->updateOrderPaymentMethod($order, $payment);

        if (!$this->shouldProcessStateUpdate($payment)) {
            return;
        }

        $this->logProcessMessage($event, 'start', $paymentId);

        if ($order->getCurrentState() !== (int) \Configuration::get(OrderStateDictionary::PAYMENT_NEW)) {
            $this->logProcessMessage($event, 'order already in different state', $paymentId);

            return;
        }

        $order->setCurrentState((int) \Configuration::get(OrderStateDictionary::PAYMENT_ACCEPTED));

        $this->logProcessMessage($event, 'finished', $paymentId);
    }

    public function supports(WebhookInterface $webhook): bool
    {
        return $webhook->getEvent() === EventNameEnum::PAYMENT_CHECKOUT_COMPLETED;
    }

    private function getPayment(string $paymentId): Payment
    {
        return $this->paymentFetcher->fetchPayment($paymentId);
    }

    private function shouldProcessStateUpdate(Payment $payment): bool
    {
        return \in_array($payment->getPaymentDetails()->getPaymentMethod(), self::SUPPORTED_PAYMENT_METHODS, true)
            && $payment->getStatus() === PaymentStatusEnum::RESERVED;
    }

    private function createOrder(EventNameEnum $event, \Cart $cart, Payment $payment): int
    {
        $paymentId = $payment->getPaymentId();

        $this->logProcessMessage($event, 'create order start', $paymentId);
        $orderId = $this->orderCreate->createFromCart($cart, $payment);
        $this->logProcessMessage($event, 'create order finished', $paymentId);

        return $orderId;
    }

    private function updateOrderPaymentMethod(\Order $order, Payment $payment): void
    {
        $paymentDetails = $payment->getPaymentDetails();
        if (!$paymentDetails instanceof PaymentDetails) {
            return;
        }

        $paymentMethod = $paymentDetails->getPaymentMethod();
        $paymentType = $paymentDetails->getPaymentType();
        $methodName = sprintf('Nexi Checkout - %s', ucfirst((string) ($paymentMethod ?? $paymentType?->value)));

        $orderPayments = $order->getOrderPaymentCollection();
        /** @var \OrderPayment $orderPayment */
        foreach ($orderPayments as $orderPayment) {
            $orderPayment->payment_method = $methodName;
            $orderPayment->save();
        }

        $order->payment = $methodName;
        $order->save();
    }
}
