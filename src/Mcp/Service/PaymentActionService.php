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

namespace Nexi\Checkout\Mcp\Service;

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Administration\Model\RefundData;
use Nexi\Checkout\Order\OrderCancel;
use Nexi\Checkout\Order\OrderCharge;
use Nexi\Checkout\Order\OrderRefund;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Nexi\Checkout\RequestBuilder\CancelRequest;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use PrestaShop\PrestaShop\Adapter\Order\Repository\OrderRepository;
use PrestaShop\PrestaShop\Core\Domain\Order\ValueObject\OrderId;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentActionService
{
    public function __construct(
        private readonly PaymentDetailsProvider $detailsProvider,
        private readonly OrderRepository $orderRepository,
        private readonly OrderCharge $orderCharge,
        private readonly OrderRefund $orderRefund,
        private readonly OrderCancel $orderCancel,
        private readonly PaymentDetailsRepository $paymentDetailsRepository,
        private readonly CancelRequest $cancelRequest,
    ) {
    }

    public function capture(int $orderId, int $amount): array
    {
        $details = $this->detailsProvider->getDetails($orderId);
        $remainingChargeAmount = $details['remainingChargeAmount'];
        if ($amount <= 0 || $amount > $remainingChargeAmount) {
            throw new \InvalidArgumentException('Amount must be positive and must not exceed the remaining eligible balance, in minor currency units.');
        }

        $order = $this->orderRepository->get(new OrderId($orderId));
        $this->requireSinglePayment($order, $details['paymentId']);

        if ($details['paymentStatus'] === PaymentStatusEnum::RESERVED->value && $amount === $details['orderAmount'] && $details['chargedAmount'] === 0) {
            if ($amount !== $remainingChargeAmount) {
                throw new \InvalidArgumentException('Reservation changed. Retrieve fresh payment details before capturing.');
            }

            $this->orderCharge->fullCharge($order);

            return $this->result('capture', $details, $amount);
        }

        $this->orderCharge->partialCharge($order, new ChargeData($amount / 100));

        return $this->result('capture', $details, $amount);
    }

    public function refund(int $orderId, int $amount): array
    {
        $details = $this->detailsProvider->getDetails($orderId);
        $this->requireStatus($details, [PaymentStatusEnum::CHARGED, PaymentStatusEnum::PARTIALLY_CHARGED, PaymentStatusEnum::PARTIALLY_REFUNDED], 'refund');
        $remainingRefundAmount = $details['remainingRefundAmount'];
        if ($amount <= 0 || $amount > $remainingRefundAmount) {
            throw new \InvalidArgumentException('Amount must be positive and must not exceed the remaining eligible balance, in minor currency units.');
        }

        $order = $this->orderRepository->get(new OrderId($orderId));

        if ($details['paymentStatus'] === PaymentStatusEnum::CHARGED->value && $details['refundedAmount'] === 0 && $amount === $details['chargedAmount']) {
            if ($details['charges'] === []) {
                throw new \InvalidArgumentException('No charges are available for refund. Retrieve fresh payment details.');
            }

            $this->orderRefund->fullRefund($order);

            return $this->result('refund', $details, $amount);
        }

        $this->orderRefund->partialRefund($order, $this->allocateRefund($orderId, $details['charges'], $amount));

        return $this->result('refund', $details, $amount);
    }

    public function cancel(int $orderId): array
    {
        $details = $this->detailsProvider->getDetails($orderId);
        $this->requireStatus($details, [PaymentStatusEnum::RESERVED], 'cancel');
        if ($details['chargedAmount'] !== 0 || $details['cancelledAmount'] !== 0) {
            throw new \InvalidArgumentException('Only an untouched reservation can be cancelled.');
        }

        $amount = $details['remainingChargeAmount'];
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive and must not exceed the remaining eligible balance, in minor currency units.');
        }

        $order = $this->orderRepository->get(new OrderId($orderId));
        $this->requireSinglePayment($order, $details['paymentId']);
        if ($this->cancelRequest->build($order)->jsonSerialize()['amount'] !== $amount) {
            throw new \InvalidArgumentException('Order amount does not match the remaining reservation. Cancellation was not submitted.');
        }

        $this->orderCancel->cancel($order);

        return $this->result('cancel', $details, $amount);
    }

    private function allocateRefund(int $orderId, array $charges, int $amount): RefundData
    {
        $refunded = $this->paymentDetailsRepository->findOneByOrderId($orderId)?->getRefundedCharges() ?? [];
        $allocation = [];
        $remaining = $amount;
        foreach ($charges as $charge) {
            $id = $charge['chargeId'];
            $available = max(0, $charge['amount'] - ($refunded[$id] ?? 0));
            $part = min($remaining, $available);
            if ($part > 0) {
                $allocation[$id] = ['amount' => $part, 'items' => []];
                $remaining -= $part;
            }
        }

        if ($remaining !== 0) {
            throw new \InvalidArgumentException('The requested refund could not be allocated to charges. Retrieve fresh payment details.');
        }

        return new RefundData($amount / 100, $allocation);
    }

    /** @param list<PaymentStatusEnum> $statuses */
    private function requireStatus(array $details, array $statuses, string $action): void
    {
        if (!in_array($details['paymentStatus'], array_map(static fn (PaymentStatusEnum $status): string => $status->value, $statuses), true)) {
            throw new \InvalidArgumentException(sprintf('Payment status %s is not eligible for %s.', $details['paymentStatus'], $action));
        }
    }

    private function requireSinglePayment(\Order $order, string $paymentId): void
    {
        $payments = [];
        foreach ($order->getOrderPaymentCollection() as $payment) {
            $payments[] = $payment;
        }

        // Existing order actions iterate every payment. Never apply an MCP amount to multiple payments.
        if (count($payments) !== 1 || $payments[0]->transaction_id !== $paymentId) {
            throw new \InvalidArgumentException('This action requires exactly one order payment matching the linked Nexi payment.');
        }
    }

    private function result(string $action, array $details, int $amount): array
    {
        return [
            'action' => $action,
            'status' => 'processed',
            'orderId' => $details['orderId'],
            'shopId' => $details['shopId'],
            'paymentId' => $details['paymentId'],
            'amount' => $amount,
            'amountUnit' => 'minor',
            'currency' => $details['currency'],
            'message' => 'Payment action service completed. Retrieve payment details to confirm the outcome; order updates may arrive by webhook.',
        ];
    }
}
