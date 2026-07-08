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

use Doctrine\ORM\EntityManagerInterface;
use Nexi\Checkout\Administration\Model\ChargeItem;
use Nexi\Checkout\Administration\Model\RefundData;
use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Helper\FormatHelper;
use Nexi\Checkout\Order\Exception\OrderChargeRefundExceeded;
use Nexi\Checkout\Order\Exception\OrderRefundException;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Nexi\Checkout\RequestBuilder\RefundRequest;
use NexiCheckout\Api\ErrorCodeEnum;
use NexiCheckout\Api\Exception\InternalErrorPaymentApiException;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Request\PartialRefundCharge;
use NexiCheckout\Model\Result\RetrievePayment\Charge;
use NexiCheckout\Model\Result\RetrievePayment\Item;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderRefund
{
    public function __construct(
        private readonly PaymentFetcherInterface $fetcher,
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly RefundRequest $refundRequest,
        private readonly PaymentDetailsRepository $paymentDetailsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormatHelper $formatHelper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws OrderChargeRefundExceeded
     * @throws OrderRefundException
     */
    public function fullRefund(\Order $order): void
    {
        $paymentDetails = $this->paymentDetailsRepository->findOneByOrderId($order->id);
        if (!$paymentDetails instanceof NexiCheckoutPaymentDetails || $paymentDetails->getPaymentId() === '') {
            throw new \LogicException(sprintf('No payment details found for order %s', $order->id));
        }

        $paymentId = $paymentDetails->getPaymentId();

        $payment = $this->fetcher->fetchPayment($paymentId);

        if ($payment->getStatus() !== PaymentStatusEnum::CHARGED) {
            $this->logger->error('Payment in incorrect status for full refund', [
                'paymentId' => $paymentId,
                'paymentStatus' => $payment->getStatus()->value,
            ]);

            return;
        }

        $shopConstraint = ShopConstraint::shop($order->getShopId());
        $paymentApi = $this->createPaymentApi($shopConstraint);
        foreach ($payment->getCharges() as $charge) {
            $refundRequest = $this->refundRequest->buildFullRefund($charge);
            $this->logger->info('Full refund request', [
                'paymentId' => $paymentId,
                'refund' => $refundRequest,
            ]);

            try {
                $response = $paymentApi->refundCharge(
                    $charge->getChargeId(),
                    $refundRequest
                );
            } catch (PaymentApiException $e) {
                $this->logger->error('Full refund failed', [
                    'paymentId' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                throw $this->createCorrespondingOrderRefundException($e, $charge->getChargeId());
            }

            $this->logger->info('Full refund success', [
                'paymentId' => $paymentId,
                'refundId' => $response->getRefundId(),
            ]);
        }
    }

    /**
     * @throws OrderChargeRefundExceeded
     * @throws OrderRefundException
     */
    public function partialRefund(\Order $order, RefundData $refundData): void
    {
        $paymentDetails = $this->paymentDetailsRepository->findOneByOrderId($order->id);
        if (!$paymentDetails instanceof NexiCheckoutPaymentDetails || $paymentDetails->getPaymentId() === '') {
            throw new \LogicException(sprintf('No payment details found for order %s', $order->id));
        }

        $paymentId = $paymentDetails->getPaymentId();
        $payment = $this->fetcher->fetchPayment($paymentId);

        if (
            !\in_array(
                $payment->getStatus(),
                [
                    PaymentStatusEnum::PARTIALLY_REFUNDED,
                    PaymentStatusEnum::PARTIALLY_CHARGED,
                    PaymentStatusEnum::CHARGED,
                ],
                true
            )) {
            $this->logger->info('Payment in incorrect status for partial refund', [
                'paymentId' => $paymentId,
                'status' => $payment->getStatus()->value,
            ]);

            return;
        }

        $shopConstraint = ShopConstraint::shop($order->getShopId());
        $paymentApi = $this->createPaymentApi($shopConstraint);

        $alreadyRefunded = $paymentDetails->getRefundedCharges();

        $charges = $refundData->getCharges();
        if ($charges === []) {
            $charges = $this->selectChargesForUnrelatedPartialRefund($refundData->getAmount(), $alreadyRefunded ?? [], $payment->getCharges() ?? []);
        }

        foreach ($charges as $chargeId => $items) {
            $partialRefund = $this->buildPartialRefund($paymentDetails->getOrderData(), $items);

            $this->logger->info('Partial refund request', [
                'paymentId' => $paymentId,
                'refund' => $partialRefund,
            ]);

            try {
                $response = $paymentApi->refundCharge($chargeId, $partialRefund);
                $this->updatePaymentDetails($paymentDetails, $chargeId, $partialRefund);
            } catch (PaymentApiException $e) {
                $this->logger->error('Partial refund failed', [
                    'paymentId' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                throw $this->createCorrespondingOrderRefundException($e, $chargeId);
            }

            $this->logger->info('Partial refund success', [
                'paymentId' => $paymentId,
                'refundId' => $response->getRefundId(),
            ]);
        }
    }

    private function createPaymentApi(ShopConstraint $shopConstraint): PaymentApi
    {
        return $this->apiFactory->create(
            $this->configurationProvider->getSecretKey($shopConstraint),
            $this->configurationProvider->isLiveMode($shopConstraint)
        );
    }

    /**
     * @param array<string, int> $alreadyRefunded
     * @param array<Charge> $charges
     *
     * @return array<string, array{amount: int, items: array<ChargeItem>}>
     */
    private function selectChargesForUnrelatedPartialRefund(float $refundAmount, array $alreadyRefunded, array $charges): array
    {
        $refundAmount = (int) ($refundAmount * 100);

        // find charge matching refundAmount
        foreach ($charges as $charge) {
            if ($charge->getAmount() === $refundAmount && !isset($alreadyRefunded[$charge->getChargeId()])) {
                return [
                    $charge->getChargeId() => [
                        'amount' => $charge->getAmount(),
                        'items' => array_map(fn (Item $orderItem): ChargeItem => new ChargeItem(
                            $charge->getChargeId(),
                            $orderItem->getName(),
                            $orderItem->getQuantity(),
                            $orderItem->getUnit(),
                            $this->formatHelper->priceToFloat($orderItem->getUnitPrice()),
                            $this->formatHelper->priceToFloat($orderItem->getGrossTotalAmount()),
                            $this->formatHelper->priceToFloat($orderItem->getNetTotalAmount()),
                            $orderItem->getReference(),
                            $orderItem->getTaxRate(),
                        ), $charge->getOrderItems()),
                    ],
                ];
            }
        }

        // calculate refund per multiple charges
        $availableCharges = [];
        $remaining = $refundAmount;

        foreach ($charges as $charge) {
            if ($remaining === 0) {
                break;
            }

            $chargeId = $charge->getChargeId();
            $availableAmount = $charge->getAmount() - ($alreadyRefunded[$chargeId] ?? 0);

            if ($availableAmount <= 0) {
                continue;
            }

            $refundAmount = min($availableAmount, $remaining);
            $availableCharges[$chargeId] = [
                'amount' => $refundAmount,
                'items' => [],
            ];

            $remaining -= $refundAmount;
        }

        return $availableCharges;
    }

    /**
     * @param array{amount: int, items: array<ChargeItem>} $items
     */
    private function buildPartialRefund(array $orderData, array $items): PartialRefundCharge
    {
        if ($items['items'] === []) {
            return $this->refundRequest->buildUnrelatedPartialRefund(
                $items['amount']
            );
        }

        return $this->refundRequest->buildPartialRefund(
            $orderData,
            $items
        );
    }

    private function createCorrespondingOrderRefundException(
        PaymentApiException $exception,
        string $chargeId,
    ): OrderRefundException {
        if (!$exception instanceof InternalErrorPaymentApiException) {
            return new OrderRefundException($chargeId, previous: $exception);
        }

        return match ($exception->getInternalCode()) {
            ErrorCodeEnum::InvalidRefundAmount => new OrderChargeRefundExceeded($chargeId, previous: $exception),
            default => new OrderRefundException($chargeId, previous: $exception),
        };
    }

    private function updatePaymentDetails(
        NexiCheckoutPaymentDetails $paymentDetails,
        string $chargeId,
        PartialRefundCharge $partialRefund,
    ): void {
        $alreadyRefunded = $paymentDetails->getRefundedCharges() ?? [];

        $paymentDetails->setRefundedCharges([
            ...$alreadyRefunded,
            ...[$chargeId => isset($alreadyRefunded[$chargeId])
                ? $alreadyRefunded[$chargeId] + $partialRefund->getAmount()
                : $partialRefund->getAmount(),
            ],
        ]);

        $this->entityManager->flush();
    }
}
