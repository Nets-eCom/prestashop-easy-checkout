<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Mcp\Service;

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Administration\Model\RefundData;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Mcp\Service\PaymentActionService;
use Nexi\Checkout\Mcp\Service\PaymentDetailsProvider;
use Nexi\Checkout\Order\OrderCancel;
use Nexi\Checkout\Order\OrderCharge;
use Nexi\Checkout\Order\OrderRefund;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Nexi\Checkout\RequestBuilder\CancelRequest;
use NexiCheckout\Model\Request\Cancel;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\Order\Repository\OrderRepository;

final class PaymentActionServiceTest extends TestCase
{
    private MockObject $details;

    private MockObject $orders;

    private MockObject $charge;

    private MockObject $refund;

    private MockObject $cancel;

    private PaymentActionService $service;

    private MockObject $order;

    private array $payments;

    private MockObject $paymentDetails;

    private MockObject $cancelRequest;

    protected function setUp(): void
    {
        $this->details = $this->createMock(PaymentDetailsProvider::class);
        $this->orders = $this->createMock(OrderRepository::class);
        $this->charge = $this->createMock(OrderCharge::class);
        $this->refund = $this->createMock(OrderRefund::class);
        $this->cancel = $this->createMock(OrderCancel::class);
        $this->order = $this->createMock(\Order::class);
        $this->order->id = 123;

        $this->payments = [(object) ['transaction_id' => 'payment-123']];
        $this->order->method('getOrderPaymentCollection')->willReturnCallback(fn (): array => $this->payments);
        $this->orders->method('get')->willReturn($this->order);
        $this->paymentDetails = $this->createMock(PaymentDetailsRepository::class);
        $this->cancelRequest = $this->createMock(CancelRequest::class);
        $this->service = new PaymentActionService($this->details, $this->orders, $this->charge, $this->refund, $this->cancel, $this->paymentDetails, $this->cancelRequest);
    }

    public function testExplicitAmountCapturesWholeReservation(): void
    {
        $this->setDetails();
        $this->charge->expects(self::once())->method('fullCharge')->with($this->order);
        $this->charge->expects(self::never())->method('partialCharge');
        $result = $this->service->capture(123, 1250);
        $this->assertSame('processed', $result['status']);
        $this->assertSame(1250, $result['amount']);
    }

    public function testPartialCaptureConvertsMinorUnitsOnce(): void
    {
        $this->setDetails();
        $this->charge->expects(self::once())->method('partialCharge')->with(
            $this->order,
            self::callback(static fn (ChargeData $data): bool => $data->getAmount() === 2.99),
        );
        $this->charge->expects(self::never())->method('fullCharge');
        $this->assertSame(299, $this->service->capture(123, 299)['amount']);
    }

    public function testRemainingCaptureAfterPartialCaptureUsesPartialService(): void
    {
        $this->setDetails(['paymentStatus' => PaymentStatusEnum::PARTIALLY_CHARGED->value, 'chargedAmount' => 1000, 'remainingChargeAmount' => 250]);
        $this->charge->expects(self::once())->method('partialCharge')->with(
            $this->order,
            self::callback(static fn (ChargeData $data): bool => $data->getAmount() === 2.5),
        );
        $this->charge->expects(self::never())->method('fullCharge');
        $this->assertSame(250, $this->service->capture(123, 250)['amount']);
    }

    public function testFullRefundUsesFullServiceOnlyWhenNothingWasRefunded(): void
    {
        $this->setDetails(['paymentStatus' => PaymentStatusEnum::CHARGED->value, 'chargedAmount' => 1250, 'remainingRefundAmount' => 1250]);
        $this->refund->expects(self::once())->method('fullRefund')->with($this->order);
        $this->refund->expects(self::never())->method('partialRefund');
        $this->assertSame(1250, $this->service->refund(123, 1250)['amount']);
    }

    public function testPartialRefund(): void
    {
        $this->setDetails(['paymentStatus' => PaymentStatusEnum::CHARGED->value, 'chargedAmount' => 1250, 'remainingRefundAmount' => 1250]);
        $this->refund->expects(self::once())->method('partialRefund')->with(
            $this->order,
            self::callback(static fn (RefundData $data): bool => $data->getAmount() === 2.99),
        );
        $this->refund->expects(self::never())->method('fullRefund');
        $this->assertSame(299, $this->service->refund(123, 299)['amount']);
    }

    public function testFullRemainingRefundAfterPreviousRefundUsesPartialService(): void
    {
        $this->setDetails(['paymentStatus' => PaymentStatusEnum::PARTIALLY_REFUNDED->value, 'chargedAmount' => 1250, 'refundedAmount' => 250, 'remainingRefundAmount' => 1000]);
        $this->refund->expects(self::once())->method('partialRefund')->with(
            $this->order,
            self::callback(static fn (RefundData $data): bool => $data->getAmount() === 10.0),
        );
        $this->refund->expects(self::never())->method('fullRefund');
        $this->assertSame(1000, $this->service->refund(123, 1000)['amount']);
    }

    #[DataProvider('partiallyRefundedCaptureAmounts')]
    public function testPartiallyRefundedPaymentCanCaptureRemainingReservation(int $amount, int $expected): void
    {
        $this->setDetails([
            'paymentStatus' => PaymentStatusEnum::PARTIALLY_REFUNDED->value,
            'orderAmount' => 2390,
            'chargedAmount' => 1500,
            'refundedAmount' => 1000,
            'remainingChargeAmount' => 890,
        ]);
        $this->charge->expects(self::never())->method('fullCharge');
        $this->charge->expects(self::once())->method('partialCharge')->with(
            $this->order,
            self::callback(static fn (ChargeData $data): bool => $data->getAmount() === (float) ($expected / 100)),
        );

        $this->assertSame($expected, $this->service->capture(123, $amount)['amount']);
    }

    public static function partiallyRefundedCaptureAmounts(): array
    {
        return [[890, 890], [500, 500]];
    }

    public function testCancellation(): void
    {
        $this->setDetails();
        $this->cancelRequest->method('build')->willReturn(new Cancel(1250));
        $this->cancel->expects(self::once())->method('cancel')->with($this->order);
        $this->assertSame('cancel', $this->service->cancel(123)['action']);
    }

    #[DataProvider('invalidActions')]
    public function testIneligibleRequestsDoNotReachPaymentActions(string $action, PaymentStatusEnum $status, int $amount): void
    {
        $this->setDetails([
            'paymentStatus' => $status->value,
            'remainingChargeAmount' => $status === PaymentStatusEnum::CANCELLED ? 0 : 1250,
        ]);
        $this->charge->expects(self::never())->method('fullCharge');
        $this->charge->expects(self::never())->method('partialCharge');
        $this->refund->expects(self::never())->method('fullRefund');
        $this->refund->expects(self::never())->method('partialRefund');
        $this->cancel->expects(self::never())->method('cancel');
        $this->expectException(\InvalidArgumentException::class);
        if ($action === 'cancel') {
            $this->service->cancel(123);

            return;
        }

        $this->service->{$action}(123, $amount);
    }

    public static function invalidActions(): array
    {
        return [
            ['capture', PaymentStatusEnum::RESERVED, 0],
            ['capture', PaymentStatusEnum::RESERVED, -1],
            ['capture', PaymentStatusEnum::RESERVED, 1251],
            ['capture', PaymentStatusEnum::CANCELLED, 100],
            ['refund', PaymentStatusEnum::CHARGED, 0],
            ['refund', PaymentStatusEnum::CHARGED, 1251],
            ['refund', PaymentStatusEnum::PENDING_REFUND, 100],
            ['cancel', PaymentStatusEnum::PARTIALLY_CHARGED, 0],
        ];
    }

    public function testMultiplePaymentsAreNotCaptured(): void
    {
        $this->setDetails();
        $this->payments[] = (object) ['transaction_id' => 'another-payment'];
        $this->charge->expects(self::never())->method('fullCharge');
        $this->expectExceptionMessage('exactly one order payment');
        $this->service->capture(123, 1250);
    }

    public function testUnlinkedTransactionIsNotCancelled(): void
    {
        $this->setDetails();
        $this->payments = [(object) ['transaction_id' => 'another-payment']];
        $this->cancel->expects(self::never())->method('cancel');
        $this->expectExceptionMessage('exactly one order payment');
        $this->service->cancel(123);
    }

    public function testShopValidationFailureStopsTheAction(): void
    {
        $this->details->method('getDetails')->willThrowException(new \InvalidArgumentException('Order not found in the current shop.'));
        $this->orders->expects(self::never())->method('get');
        $this->charge->expects(self::never())->method('fullCharge');
        $this->expectExceptionMessage('Order not found in the current shop.');
        $this->service->capture(123, 1250);
    }

    public function testCancellationAmountMismatchStopsTheAction(): void
    {
        $this->setDetails();
        $this->cancelRequest->method('build')->willReturn(new Cancel(1000));
        $this->cancel->expects(self::never())->method('cancel');
        $this->expectExceptionMessage('does not match');
        $this->service->cancel(123);
    }

    public function testFullRefundWithoutChargesStopsTheAction(): void
    {
        $this->setDetails(['paymentStatus' => PaymentStatusEnum::CHARGED->value, 'chargedAmount' => 1250, 'remainingRefundAmount' => 1250, 'charges' => []]);
        $this->refund->expects(self::never())->method('fullRefund');
        $this->expectExceptionMessage('No charges');
        $this->service->refund(123, 1250);
    }

    public function testInsufficientChargeAllocationStopsBeforeRefunding(): void
    {
        $this->setDetails(['paymentStatus' => PaymentStatusEnum::CHARGED->value, 'chargedAmount' => 1250, 'remainingRefundAmount' => 1250, 'charges' => [['chargeId' => 'charge-1', 'amount' => 100]]]);
        $this->refund->expects(self::never())->method('partialRefund');
        $this->expectExceptionMessage('could not be allocated');
        $this->service->refund(123, 299);
    }

    public function testRefundAllocationPreservesMinorUnitsAndPreviouslyRefundedAmounts(): void
    {
        $this->setDetails([
            'paymentStatus' => PaymentStatusEnum::PARTIALLY_REFUNDED->value,
            'chargedAmount' => 1000,
            'refundedAmount' => 100,
            'remainingRefundAmount' => 900,
            'charges' => [['chargeId' => 'charge-1', 'amount' => 200], ['chargeId' => 'charge-2', 'amount' => 800]],
        ]);
        $stored = $this->createMock(NexiCheckoutPaymentDetails::class);
        $stored->method('getRefundedCharges')->willReturn(['charge-1' => 100]);
        $this->paymentDetails->method('findOneByOrderId')->with(123)->willReturn($stored);
        $this->refund->expects(self::once())->method('partialRefund')->with(
            $this->order,
            self::callback(static fn (RefundData $data): bool => $data->getAmount() === 2.99 && $data->getCharges() === [
                'charge-1' => ['amount' => 100, 'items' => []],
                'charge-2' => ['amount' => 199, 'items' => []],
            ]),
        );
        $this->service->refund(123, 299);
    }

    public function testFullCaptureRejectsInconsistentReservation(): void
    {
        $this->setDetails(['remainingChargeAmount' => 1500]);
        $this->charge->expects(self::never())->method('fullCharge');
        $this->expectExceptionMessage('Reservation changed');
        $this->service->capture(123, 1250);
    }

    private function setDetails(array $overrides = []): void
    {
        $this->details->method('getDetails')->with(123)->willReturn(array_replace([
            'orderId' => 123,
            'shopId' => 1,
            'paymentId' => 'payment-123',
            'paymentStatus' => PaymentStatusEnum::RESERVED->value,
            'orderAmount' => 1250,
            'chargedAmount' => 0,
            'refundedAmount' => 0,
            'cancelledAmount' => 0,
            'remainingChargeAmount' => 1250,
            'remainingRefundAmount' => 0,
            'currency' => 'EUR',
            'charges' => [['chargeId' => 'charge-1', 'amount' => 1250]],
        ], $overrides));
    }
}
