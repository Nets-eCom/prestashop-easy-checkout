<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Mcp\Service;

use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Mcp\Service\PaymentDetailsProvider;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use NexiCheckout\Model\Result\RetrievePayment\OrderDetails;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiCheckout\Model\Result\RetrievePayment\Summary;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\Order\Repository\OrderRepository;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;

final class PaymentDetailsProviderTest extends TestCase
{
    public function testRejectsOrderFromAnotherShopBeforeFetchingPayment(): void
    {
        $order = new \Order(123);
        $order->id_shop = 2;

        $orders = $this->createMock(OrderRepository::class);
        $orders->method('get')->willReturn($order);
        $details = $this->createMock(PaymentDetailsRepository::class);
        $details->expects(self::never())->method('findOneByOrderId');
        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects(self::never())->method('fetchPayment');

        $this->expectExceptionMessage('Order not found in the current shop.');
        $this->provider($orders, $details, $fetcher)->getDetails(123);
    }

    public function testRejectsInvalidIdBeforeLoadingOrder(): void
    {
        $orders = $this->createMock(OrderRepository::class);
        $orders->expects(self::never())->method('get');

        $this->expectException(\InvalidArgumentException::class);
        $this->provider($orders)->getDetails(0);
    }

    public function testMissingOrderProducesReadableError(): void
    {
        $orders = $this->createMock(OrderRepository::class);
        $orders->method('get')->willThrowException(new OrderNotFoundException());

        $this->expectExceptionMessage('Order not found in the current shop.');
        $this->provider($orders)->getDetails(123);
    }

    public function testDoesNotFetchAnUnlinkedPayment(): void
    {
        $orders = $this->createMock(OrderRepository::class);
        $orders->method('get')->willReturn(new \Order(123));
        $details = $this->createMock(PaymentDetailsRepository::class);
        $details->method('findOneByOrderId')->willReturn(null);
        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects(self::never())->method('fetchPayment');

        $this->expectExceptionMessage('No Nexi payment is linked to this order.');
        $this->provider($orders, $details, $fetcher)->getDetails(123);
    }

    public function testReturnsMinorUnitBalancesWithoutCustomerData(): void
    {
        $orders = $this->createMock(OrderRepository::class);
        $orders->method('get')->willReturn(new \Order(123));
        $details = $this->createMock(NexiCheckoutPaymentDetails::class);
        $details->method('getPaymentId')->willReturn('payment-123');
        $details->method('getOrderData')->willReturn([
            'consumer' => ['email' => 'private@example.com'],
            'items' => [['reference' => 'sku-1', 'quantity' => 1, 'grossTotalAmount' => 1250, 'unexpected' => 'private']],
        ]);
        $repository = $this->createMock(PaymentDetailsRepository::class);
        $repository->method('findOneByOrderId')->with(123)->willReturn($details);
        $summary = $this->createMock(Summary::class);
        $summary->method('getReservedAmount')->willReturn(1250);
        $summary->method('getChargedAmount')->willReturn(1000);
        $summary->method('getRefundedAmount')->willReturn(200);
        $orderDetails = $this->createMock(OrderDetails::class);
        $orderDetails->method('getAmount')->willReturn(1250);
        $orderDetails->method('getCurrency')->willReturn('EUR');
        $payment = $this->createMock(Payment::class);
        $payment->method('getSummary')->willReturn($summary);
        $payment->method('getOrderDetails')->willReturn($orderDetails);
        $payment->method('getStatus')->willReturn(PaymentStatusEnum::PARTIALLY_REFUNDED);
        $payment->method('getCreated')->willReturn(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $payment->expects(self::never())->method('getConsumer');
        $payment->expects(self::never())->method('getCheckout');
        $fetcher = $this->createMock(PaymentFetcherInterface::class);
        $fetcher->expects(self::once())->method('fetchPayment')->with('payment-123')->willReturn($payment);

        $result = $this->provider($orders, $repository, $fetcher)->getDetails(123);

        $this->assertSame('minor', $result['amountUnit']);
        $this->assertSame('EUR', $result['currency']);
        $this->assertSame(250, $result['remainingChargeAmount']);
        $this->assertSame(800, $result['remainingRefundAmount']);
        $this->assertSame([['reference' => 'sku-1', 'quantity' => 1, 'grossTotalAmount' => 1250]], $result['items']);
        $this->assertArrayNotHasKey('consumer', $result);
    }

    private function provider(
        OrderRepository $orders,
        ?PaymentDetailsRepository $details = null,
        ?PaymentFetcherInterface $fetcher = null,
    ): PaymentDetailsProvider {
        $context = new \Context();
        $context->shop = new \Shop(1);

        return new PaymentDetailsProvider(
            $orders,
            $details ?? $this->createMock(PaymentDetailsRepository::class),
            $fetcher ?? $this->createMock(PaymentFetcherInterface::class),
            $context,
        );
    }
}
