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

namespace Nexi\Checkout\Order;

use Nexi\Checkout\Configuration\OrderStateDictionary;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class BulkOrderCapture
{
    public const MAX_ORDERS = 20;

    public function __construct(
        private PaymentDetailsRepository $paymentDetailsRepository,
        private PaymentFetcherInterface $paymentFetcher,
        private OrderCharge $orderCharge,
        private \Context $context,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<\Order> $orders
     */
    public function capture(array $orders): BulkCaptureResult
    {
        if (count($orders) > self::MAX_ORDERS) {
            throw new \InvalidArgumentException(sprintf('A maximum of %d orders can be captured at once.', self::MAX_ORDERS));
        }

        $capturedOrderIds = [];
        $skippedOrderIds = [];
        $failedOrderIds = [];
        foreach ($orders as $order) {
            $orderId = (int) $order->id;
            $details = $this->paymentDetailsRepository->findOneByOrderId($orderId);

            if (
                !$details instanceof NexiCheckoutPaymentDetails
                || $order->getCurrentState() !== (int) \Configuration::get(OrderStateDictionary::PAYMENT_ACCEPTED)
            ) {
                $skippedOrderIds[] = $orderId;

                continue;
            }

            $originalShop = $this->context->shop;
            try {
                if ((int) $originalShop->id !== (int) $order->id_shop) {
                    $this->context->shop = new \Shop((int) $order->id_shop);
                }

                $payment = $this->paymentFetcher->fetchPayment($details->getPaymentId());
                if ($payment->getStatus() !== PaymentStatusEnum::RESERVED) {
                    $skippedOrderIds[] = $orderId;

                    continue;
                }

                $this->orderCharge->fullCharge($order);
                $capturedOrderIds[] = $orderId;
            } catch (\Throwable $exception) {
                $failedOrderIds[] = $orderId;
                $this->logFailure($orderId, $exception);
            } finally {
                $this->context->shop = $originalShop;
            }
        }

        return new BulkCaptureResult($capturedOrderIds, $skippedOrderIds, $failedOrderIds);
    }

    private function logFailure(int $orderId, \Throwable $exception): void
    {
        $this->logger->error('Bulk capture failed for order.', [
            'orderId' => $orderId,
            'exception' => $exception,
        ]);
    }
}
