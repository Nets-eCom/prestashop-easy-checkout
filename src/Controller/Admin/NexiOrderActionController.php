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

namespace Nexi\Checkout\Controller\Admin;

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Administration\Model\RefundData;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\Exception\OrderChargeException;
use Nexi\Checkout\Order\Exception\OrderRefundException;
use Nexi\Checkout\Order\OrderCancel;
use Nexi\Checkout\Order\OrderCharge;
use Nexi\Checkout\Order\OrderRefund;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use PrestaShop\PrestaShop\Adapter\Order\Repository\OrderRepository;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\ValueObject\OrderId;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

if (!defined('_PS_VERSION_')) {
    exit;
}

class NexiOrderActionController extends PrestaShopAdminController
{
    public function __construct(
        private readonly OrderCancel $orderCancel,
        private readonly PaymentFetcherInterface $fetcher,
        private readonly OrderRefund $orderRefund,
        private readonly OrderRepository $orderRepository,
        private readonly OrderCharge $orderCharge,
        private readonly PaymentDetailsRepository $paymentDetailsRepository,
        private readonly \Context $context,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AdminSecurity(
        "is_granted('update', 'AdminOrders')",
        message: 'You do not have permission to perform action',
    )]
    public function cancel(int $orderId): JsonResponse
    {
        try {
            $order = new \Order($orderId);

            if (!\Validate::isLoadedObject($order)) {
                $this->addFlash(
                    'error',
                    $this->trans('Order not found.', [], 'Modules.Nexicheckout.AdminOrder')
                );

                return $this->json([
                    'message' => 'Order not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->orderCancel->cancel($order);

            $this->addFlash(
                'success',
                $this->trans('Payment has been cancelled successfully.', [], 'Modules.Nexicheckout.AdminOrder')
            );

            return $this->json([]);
        } catch (\Exception $exception) {
            return $this->handleErrorResponse(
                $exception,
                $this->trans('Payment has not been cancelled.', [], 'Modules.Nexicheckout.AdminOrder'),
                sprintf('Error cancelling payment for order %s.', $orderId),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['orderId' => $orderId]
            );
        }
    }

    #[AdminSecurity(
        "is_granted('update', 'AdminOrders')",
        message: 'You do not have permission to perform action',
    )]
    public function charge(
        int $orderId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ChargeData $chargeData,
    ): JsonResponse {
        try {
            $order = new \Order($orderId);

            if (!\Validate::isLoadedObject($order)) {
                return $this->json([
                    'message' => 'Order not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->processCharge($order, $chargeData);

            $this->addFlash(
                'success',
                $this->trans('Payment has been charged successfully.', [], 'Modules.Nexicheckout.AdminOrder')
            );

            return $this->json([]);
        } catch (\LogicException|OrderChargeException|\Exception $exception) {
            return $this->handleErrorResponse(
                $exception,
                $this->trans('Payment has not been charged.', [], 'Modules.Nexicheckout.AdminOrder'),
                sprintf('Error charging payment for order %s.', $orderId),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['orderId' => $orderId]
            );
        }
    }

    #[AdminSecurity(
        "is_granted('update', 'AdminOrders')",
        message: 'You do not have permission to perform action',
        redirectQueryParamsToKeep: ['orderId'],
        redirectRoute: 'admin_orders_view'
    )]
    public function refund(
        int $orderId,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        RefundData $refundData,
    ): JsonResponse {
        $orderId = new OrderId($orderId);
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (OrderNotFoundException) {
            return $this->json([], Response::HTTP_NOT_FOUND);
        }

        $refundData->setContext($this->context);

        try {
            $this->processRefund($order, $refundData);

            $this->addFlash(
                'success',
                $this->trans('Payment has been refunded successfully.', [], 'Modules.Nexicheckout.AdminOrder')
            );
        } catch (OrderRefundException $orderRefundException) {
            return $this->handleErrorResponse(
                $orderRefundException,
                $this->trans('Payment has not been refunded.', [], 'Modules.Nexicheckout.AdminOrder'),
                sprintf('Error refunding payment for order %s.', $orderId->getValue()),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['orderId' => $orderId]
            );
        }

        return $this->json([]);
    }

    #[AdminSecurity(
        "is_granted('read', 'AdminOrders')",
        message: 'You do not have permission to view payment details',
    )]
    public function paymentDetails(int $orderId): Response
    {
        $order = new \Order($orderId);

        if (!\Validate::isLoadedObject($order)) {
            return $this->json([
                'message' => 'Order not found',
            ], 404);
        }

        /** @var \OrderPayment $orderPayment */
        $orderPayment = $order->getOrderPaymentCollection()->getFirst();
        $paymentId = $orderPayment->transaction_id;
        $payment = $this->fetcher->fetchPayment($paymentId);

        $summary = $payment->getSummary();
        $orderAmount = $payment->getOrderDetails()->getAmount();
        $chargedAmount = $summary->getChargedAmount();
        $refundedAmount = $summary->getRefundedAmount();
        $status = $payment->getStatus();
        $paymentDetailsEntity = $this->paymentDetailsRepository->findOneByOrderId($orderId);
        $orderData = $paymentDetailsEntity?->getOrderData() ?? [];

        $remainingChargeAmount = $status !== PaymentStatusEnum::CANCELLED ? $orderAmount - $chargedAmount : 0;
        $remainingRefundAmount = $status !== PaymentStatusEnum::CANCELLED ? $chargedAmount - $refundedAmount : 0;

        return $this->json([
            'paymentId' => $paymentId,
            'paymentMethod' => $orderPayment->payment_method,
            'paymentVia' => $payment->getPaymentDetails()?->getPaymentMethod(),
            'orderAmount' => $this->formatAmount($orderAmount),
            'orderTime' => $payment->getCreated()->format('Y-m-d H:i:s'),
            'chargedAmount' => $this->formatAmount($chargedAmount),
            'remainingChargeAmount' => $this->formatAmount($remainingChargeAmount),
            'refundedAmount' => $this->formatAmount($refundedAmount),
            'remainingRefundAmount' => $this->formatAmount($remainingRefundAmount),
            'paymentStatus' => $status->value,
            // 'orderItems' => $this->buildItems($payment, $transaction),
            'charges' => $this->buildChargedItems($payment),
            'currency' => \Currency::getIsoCodeById($order->id_currency),
            'items' => $this->buildItems($orderData, $payment),
        ]);
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    private function processRefund(\Order $order, RefundData $refundData): void
    {
        if ($refundData->getAmount() < (float) $order->total_paid_tax_incl) {
            $this->orderRefund->partialRefund($order, $refundData);

            return;
        }

        $this->orderRefund->fullRefund($order);
    }

    private function processCharge(\Order $order, ChargeData $chargeData): void
    {
        if ($chargeData->getAmount() < $order->getTotalPaid()) {
            $this->orderCharge->partialCharge($order, $chargeData);

            return;
        }

        $this->orderCharge->fullCharge($order);
    }

    private function buildItems(array $orderData, Payment $payment): array
    {
        $items = $orderData['items'] ?? [];
        $charges = $payment->getCharges() ?? [];

        return array_map(
            function (array $item) use ($charges): array {
                $qtyCharged = 0;

                foreach ($charges as $charge) {
                    foreach ($charge->getOrderItems() as $chargedItem) {
                        if ($chargedItem->getReference() === $item['reference']) {
                            $qtyCharged += $chargedItem->getQuantity();
                        }
                    }
                }

                $unitGrossPrice = (int) $item['grossTotalAmount'] / (int) $item['quantity'];

                return [
                    'reference' => $item['reference'],
                    'name' => $item['name'],
                    'quantity' => (int) $item['quantity'],
                    'unitPrice' => $this->formatAmount($unitGrossPrice),
                    'grossTotalAmount' => $this->formatAmount((int) $item['grossTotalAmount']),
                    'qtyCharged' => $qtyCharged,
                ];
            },
            $items
        );
    }

    /**
     * @return list<array{
     *      chargeId: string,
     *      name: string,
     *      unit: string,
     *      quantity: int|float,
     *      unitPrice: string,
     *      grossTotalAmount: string,
     *      netTotalAmount: string,
     *      reference: string,
     *      taxRate: string|null
     * }>
     */
    private function buildChargedItems(Payment $payment): array
    {
        $charges = $payment->getCharges();

        if ($charges === null) {
            return [];
        }

        $chargedItems = [];

        foreach ($charges as $charge) {
            foreach ($charge->getOrderItems() as $chargedItem) {
                $chargedItems[] = [
                    'chargeId' => $charge->getChargeId(),
                    'name' => $chargedItem->getName(),
                    'unit' => $chargedItem->getUnit(),
                    'quantity' => $chargedItem->getQuantity(),
                    'unitPrice' => $this->formatAmount($chargedItem->getUnitPrice()),
                    'grossTotalAmount' => $this->formatAmount($chargedItem->getGrossTotalAmount()),
                    'netTotalAmount' => $this->formatAmount($chargedItem->getNetTotalAmount()),
                    'reference' => $chargedItem->getReference(),
                    'taxRate' => $chargedItem->getTaxRate() !== null ? $this->formatAmount($chargedItem->getTaxRate()) : null,
                ];
            }
        }

        return $chargedItems;
    }

    /**
     * Handles error responses by logging the error, adding it to session and returning a JSON response with an error message.
     *
     * @param \Throwable $exception the exception that occurred
     * @param string $errorMessage the error message to be added to session and returned in the JSON response
     * @param string $logMessage the message to be logged
     * @param int $statusCode The HTTP status code for the JSON response. Default is 500 (Internal Server Error).
     * @param array $context Additional context to be logged with the error message. Default is an empty array.
     *
     * @return JsonResponse a JSON response containing the error message and the specified HTTP status code
     */
    private function handleErrorResponse(
        \Throwable $exception,
        string $errorMessage,
        string $logMessage,
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        array $context = [],
    ): JsonResponse {
        $this->logger->error($logMessage, $context + ['exception' => $exception]);

        $this->addFlash(
            'error',
            $errorMessage
        );

        return $this->json([
            'message' => $errorMessage,
        ], $statusCode);
    }
}
