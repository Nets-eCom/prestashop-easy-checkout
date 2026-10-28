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

use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use NexiCheckout\Model\Result\RetrievePayment\Charge;
use NexiCheckout\Model\Result\RetrievePayment\Item;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use PrestaShop\PrestaShop\Adapter\Order\Repository\OrderRepository;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\ValueObject\OrderId;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentDetailsProvider
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly PaymentDetailsRepository $paymentDetailsRepository,
        private readonly PaymentFetcherInterface $paymentFetcher,
        private readonly \Context $context,
    ) {
    }

    public function getDetails(int $orderId): array
    {
        if ($orderId <= 0) {
            throw new \InvalidArgumentException('Order ID must be a positive integer.');
        }

        try {
            $order = $this->orderRepository->get(new OrderId($orderId));
        } catch (OrderNotFoundException) {
            throw new \InvalidArgumentException('Order not found in the current shop.');
        }

        // The fetcher selects credentials from this same shop context.
        if ((int) ($this->context->shop->id ?? 0) <= 0 || (int) $order->id_shop !== (int) $this->context->shop->id) {
            throw new \InvalidArgumentException('Order not found in the current shop.');
        }

        $details = $this->paymentDetailsRepository->findOneByOrderId($orderId);
        if (!$details instanceof NexiCheckoutPaymentDetails || $details->getPaymentId() === '') {
            throw new \InvalidArgumentException('No Nexi payment is linked to this order.');
        }

        $payment = $this->paymentFetcher->fetchPayment($details->getPaymentId());
        $summary = $payment->getSummary();
        $status = $payment->getStatus();
        $itemKeys = array_flip([
            'reference', 'name', 'quantity', 'unit', 'unitPrice', 'grossTotalAmount', 'netTotalAmount', 'taxRate', 'taxAmount',
        ]);

        return [
            'orderId' => $orderId,
            'shopId' => (int) $order->id_shop,
            'paymentId' => $details->getPaymentId(),
            'paymentStatus' => $status->value,
            'paymentMethod' => $payment->getPaymentDetails()?->getPaymentMethod(),
            'currency' => $payment->getOrderDetails()->getCurrency(),
            'amountUnit' => 'minor',
            'orderAmount' => $payment->getOrderDetails()->getAmount(),
            'reservedAmount' => $summary->getReservedAmount(),
            'chargedAmount' => $summary->getChargedAmount(),
            'refundedAmount' => $summary->getRefundedAmount(),
            'cancelledAmount' => $summary->getCancelledAmount(),
            'remainingChargeAmount' => $status === PaymentStatusEnum::CANCELLED ? 0 : max(0, $summary->getReservedAmount() - $summary->getChargedAmount() - $summary->getCancelledAmount()),
            'remainingRefundAmount' => max(0, $summary->getChargedAmount() - $summary->getRefundedAmount()),
            'createdAt' => $payment->getCreated()->format(DATE_ATOM),
            'items' => array_map(
                static fn (array $item): array => array_intersect_key($item, $itemKeys),
                $details->getOrderData()['items'] ?? [],
            ),
            'charges' => array_map(fn (Charge $charge): array => [
                'chargeId' => $charge->getChargeId(),
                'amount' => $charge->getAmount(),
                'createdAt' => $charge->getCreated()->format(DATE_ATOM),
                'items' => array_map($this->formatItem(...), $charge->getOrderItems()),
            ], $payment->getCharges() ?? []),
        ];
    }

    private function formatItem(Item $item): array
    {
        return [
            'reference' => $item->getReference(),
            'name' => $item->getName(),
            'quantity' => $item->getQuantity(),
            'unit' => $item->getUnit(),
            'unitPrice' => $item->getUnitPrice(),
            'grossTotalAmount' => $item->getGrossTotalAmount(),
            'netTotalAmount' => $item->getNetTotalAmount(),
            'taxRate' => $item->getTaxRate(),
            'taxAmount' => $item->getTaxAmount(),
        ];
    }
}
