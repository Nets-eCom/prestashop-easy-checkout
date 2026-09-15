<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Order;

use Nexi\Checkout\Configuration\OrderStateDictionary;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\BulkOrderCapture;
use Nexi\Checkout\Order\Exception\OrderChargeException;
use Nexi\Checkout\Order\OrderCharge;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BulkOrderCaptureTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::$values = [
            OrderStateDictionary::PAYMENT_ACCEPTED => 15,
        ];
    }

    public function testCaptureContinuesAfterSkippedAndFailedOrders(): void
    {
        $orders = [
            1 => $this->createOrder(1, 1),
            2 => $this->createOrder(2, 1),
            3 => $this->createOrder(3, 2),
            4 => $this->createOrder(4, 1),
        ];

        $paymentDetailsRepository = $this->createMock(PaymentDetailsRepository::class);
        $paymentDetailsRepository->method('findOneByOrderId')
            ->willReturnCallback(fn (int $orderId): ?NexiCheckoutPaymentDetails => match ($orderId) {
                1, 4 => $this->createMock(NexiCheckoutPaymentDetails::class),
                default => null,
            });

        $orderCharge = $this->createMock(OrderCharge::class);
        $orderCharge->method('fullCharge')
            ->willReturnCallback(static function (\Order $order): void {
                if ($order->id === 4) {
                    throw new \RuntimeException('Gateway unavailable');
                }
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Bulk capture failed for order.', self::callback(
                static fn (array $context): bool => $context['orderId'] === 4
                    && $context['exception'] instanceof \RuntimeException
            ));

        $result = $this->createBulkCapture(
            $paymentDetailsRepository,
            $this->createPaymentFetcher(),
            $orderCharge,
            $logger
        )->capture(array_values($orders));

        $this->assertSame([1], $result->getCapturedOrderIds());
        $this->assertSame([2, 3], $result->getSkippedOrderIds());
        $this->assertSame([4], $result->getFailedOrderIds());
    }

    public function testChargeExceptionIsFailed(): void
    {
        $order = $this->createOrder(2, 1);
        $paymentDetailsRepository = $this->createMock(PaymentDetailsRepository::class);
        $paymentDetailsRepository->method('findOneByOrderId')
            ->willReturn($this->createMock(NexiCheckoutPaymentDetails::class));

        $orderCharge = $this->createMock(OrderCharge::class);
        $orderCharge->expects(self::once())
            ->method('fullCharge')
            ->with($order)
            ->willThrowException(new OrderChargeException('payment-2'));

        $result = $this->createBulkCapture(
            $paymentDetailsRepository,
            $this->createPaymentFetcher(),
            $orderCharge,
            $this->createMock(LoggerInterface::class)
        )->capture([$order]);

        $this->assertSame([], $result->getCapturedOrderIds());
        $this->assertSame([], $result->getSkippedOrderIds());
        $this->assertSame([2], $result->getFailedOrderIds());
    }

    public function testSelectionCannotExceedMaximum(): void
    {
        $bulkCapture = $this->createBulkCapture(
            $this->createMock(PaymentDetailsRepository::class),
            $this->createPaymentFetcher(),
            $this->createMock(OrderCharge::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(\InvalidArgumentException::class);
        $bulkCapture->capture(array_map(
            fn (int $orderId): \Order => $this->createOrder($orderId, 1),
            range(1, BulkOrderCapture::MAX_ORDERS + 1)
        ));
    }

    public function testCaptureUsesTheOrderShopConfiguration(): void
    {
        $order = $this->createOrder(1, 2);
        $paymentDetailsRepository = $this->createMock(PaymentDetailsRepository::class);
        $paymentDetailsRepository->method('findOneByOrderId')
            ->willReturn($this->createMock(NexiCheckoutPaymentDetails::class));

        $context = new \Context();
        $context->shop = new \Shop(1);

        $orderCharge = $this->createMock(OrderCharge::class);
        $orderCharge->expects(self::once())
            ->method('fullCharge')
            ->with($order)
            ->willReturnCallback(function () use ($context): void {
                $this->assertSame(2, $context->shop->id);
            });

        $result = $this->createBulkCapture(
            $paymentDetailsRepository,
            $this->createPaymentFetcher(),
            $orderCharge,
            $this->createMock(LoggerInterface::class),
            $context
        )->capture([$order]);

        $this->assertSame([1], $result->getCapturedOrderIds());
        $this->assertSame(1, $context->shop->id);
    }

    public function testOrderWithWrongStatusIsSkipped(): void
    {
        $order = $this->createOrder(1, 1, 11);

        $paymentDetailsRepository = $this->createMock(PaymentDetailsRepository::class);
        $paymentDetailsRepository->method('findOneByOrderId')
            ->willReturn($this->createMock(NexiCheckoutPaymentDetails::class));

        $paymentFetcher = $this->createMock(PaymentFetcherInterface::class);
        $paymentFetcher->expects($this->never())
            ->method('fetchPayment');

        $orderCharge = $this->createMock(OrderCharge::class);
        $orderCharge->expects($this->never())
            ->method('fullCharge');

        $result = $this->createBulkCapture(
            $paymentDetailsRepository,
            $paymentFetcher,
            $orderCharge,
            $this->createMock(LoggerInterface::class)
        )->capture([$order]);

        $this->assertSame([], $result->getCapturedOrderIds());
        $this->assertSame([1], $result->getSkippedOrderIds());
        $this->assertSame([], $result->getFailedOrderIds());
    }

    public function testNonReservedPaymentIsSkipped(): void
    {
        $order = $this->createOrder(1, 1);

        $details = $this->createMock(NexiCheckoutPaymentDetails::class);
        $details->method('getPaymentId')->willReturn('payment-1');

        $paymentDetailsRepository = $this->createMock(PaymentDetailsRepository::class);
        $paymentDetailsRepository->method('findOneByOrderId')->willReturn($details);

        $orderCharge = $this->createMock(OrderCharge::class);
        $orderCharge->expects($this->never())
            ->method('fullCharge');

        $result = $this->createBulkCapture(
            $paymentDetailsRepository,
            $this->createPaymentFetcher(PaymentStatusEnum::CHARGED),
            $orderCharge,
            $this->createMock(LoggerInterface::class)
        )->capture([$order]);

        $this->assertSame([], $result->getCapturedOrderIds());
        $this->assertSame([1], $result->getSkippedOrderIds());
        $this->assertSame([], $result->getFailedOrderIds());
    }

    private function createBulkCapture(
        PaymentDetailsRepository $paymentDetailsRepository,
        PaymentFetcherInterface $paymentFetcher,
        OrderCharge $orderCharge,
        LoggerInterface $logger,
        ?\Context $context = null,
    ): BulkOrderCapture {
        $context ??= new \Context();
        $context->shop ??= new \Shop(1);

        return new BulkOrderCapture(
            $paymentDetailsRepository,
            $paymentFetcher,
            $orderCharge,
            $context,
            $logger
        );
    }

    private function createPaymentFetcher(PaymentStatusEnum $status = PaymentStatusEnum::RESERVED): PaymentFetcherInterface
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getStatus')->willReturn($status);

        $paymentFetcher = $this->createMock(PaymentFetcherInterface::class);
        $paymentFetcher->method('fetchPayment')->willReturn($payment);

        return $paymentFetcher;
    }

    private function createOrder(int $orderId, int $shopId, int $stateId = 15): \Order
    {
        $order = new \Order($orderId);
        $order->id = $orderId;
        $order->id_shop = $shopId;
        $order->current_state = $stateId;

        return $order;
    }
}
