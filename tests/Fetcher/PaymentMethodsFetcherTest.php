<?php

namespace Nexi\Checkout\Tests\Fetcher;

use Nexi\Checkout\Fetcher\PaymentMethodsFetcher;
use Nexi\Checkout\Order\Provider\PaymentApiProvider;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Model\Request\PaymentMethods;
use NexiCheckout\Model\Result\PaymentMethodsResult\PaymentMethod;
use NexiCheckout\Model\Result\PaymentMethodsResult\PaymentMethodsResult;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PrestaShopBundle\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;

class PaymentMethodsFetcherTest extends TestCase
{
    private PaymentMethodsFetcher $paymentMethodsFetcher;

    private MockObject $paymentApiProvider;

    private MockObject $context;

    private MockObject $logger;

    private MockObject $paymentApi;

    protected function setUp(): void
    {
        $this->paymentApiProvider = $this->createMock(PaymentApiProvider::class);
        $this->context = $this->createMock(\Context::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->paymentApi = $this->createMock(PaymentApi::class);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(fn ($id) => $id);

        $this->context->method('getTranslator')->willReturn($translator);

        $this->paymentMethodsFetcher = new PaymentMethodsFetcher(
            $this->paymentApiProvider,
            $this->context,
            $this->logger
        );
    }

    public function testGetAvailablePaymentMethodsSuccess(): void
    {
        $methods = [
            new PaymentMethod('Paypal', 'Other', '', true),
            new PaymentMethod('SomeOption', 'Other', '', true),
        ];

        $paymentMethodResult = $this->createPaymentMethodsResult($methods);

        $this->expectCreatePaymentApi();
        $this->paymentApi->expects($this->once())
            ->method('getPaymentMethods')
            ->with($this->paymentMethodsRequestMatcher('DK'))
            ->willReturn($paymentMethodResult);
        $this->logger->expects($this->never())->method('error');

        $expected = [
            'Card' => ['value' => 'Card', 'label' => 'Card'],
            'Paypal' => ['value' => 'Paypal', 'label' => 'Paypal'],
            'SomeOption' => ['value' => 'SomeOption', 'label' => 'SomeOption'],
        ];

        $result = $this->paymentMethodsFetcher->getAvailablePaymentMethods('DK');
        $this->assertSame($expected, $result);
    }

    /**
     * @dataProvider exceptionProvider
     */
    public function testGetAvailablePaymentMethodsHandlesExceptions(\Throwable $exception, string $expectedLogMessage): void
    {
        $this->expectCreatePaymentApi();
        $this->paymentApi->expects($this->once())
            ->method('getPaymentMethods')
            ->with($this->paymentMethodsRequestMatcher('USD'))
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($expectedLogMessage);

        $this->assertSame([], $this->paymentMethodsFetcher->getAvailablePaymentMethods('USD'));
    }

    public function testGetAvailablePaymentMethodsEmptyResult(): void
    {
        $paymentMethodResult = $this->createPaymentMethodsResult([]);

        $this->expectCreatePaymentApi();
        $this->paymentApi->expects($this->once())
            ->method('getPaymentMethods')
            ->with($this->paymentMethodsRequestMatcher('USD'))
            ->willReturn($paymentMethodResult);
        $this->logger->expects($this->never())->method('error');

        $expected = [
            'Card' => ['value' => 'Card', 'label' => 'Card'],
        ];

        $result = $this->paymentMethodsFetcher->getAvailablePaymentMethods('USD');
        $this->assertSame($expected, $result);
    }

    public function testGetAvailablePaymentMethodsSkipsDuplicateMethods(): void
    {
        $methods = [
            new PaymentMethod('Card', 'Card', '', true),
            new PaymentMethod('SomeDoubledOption', 'Other', '', true),
            new PaymentMethod('SomeDoubledOption', 'Other', '', true),
        ];

        $paymentMethodResult = $this->createPaymentMethodsResult($methods);

        $this->expectCreatePaymentApi();
        $this->paymentApi->expects($this->once())
            ->method('getPaymentMethods')
            ->with($this->paymentMethodsRequestMatcher(null))
            ->willReturn($paymentMethodResult);
        $this->logger->expects($this->never())->method('error');

        $expected = [
            'Card' => ['value' => 'Card', 'label' => 'Card'],
            'SomeDoubledOption' => ['value' => 'SomeDoubledOption', 'label' => 'SomeDoubledOption'],
        ];

        $result = $this->paymentMethodsFetcher->getAvailablePaymentMethods();
        $this->assertSame($expected, $result);
    }

    public function testGetAvailablePaymentMethodsUsesNullCurrencyByDefault(): void
    {
        $paymentMethodResult = $this->createPaymentMethodsResult([]);

        $this->expectCreatePaymentApi();
        $this->paymentApi->expects($this->once())
            ->method('getPaymentMethods')
            ->with($this->paymentMethodsRequestMatcher(null))
            ->willReturn($paymentMethodResult);
        $this->logger->expects($this->never())->method('error');

        $this->assertSame(
            ['Card' => ['value' => 'Card', 'label' => 'Card']],
            $this->paymentMethodsFetcher->getAvailablePaymentMethods()
        );
    }

    public static function exceptionProvider(): array
    {
        return [
            'payment api exception' => [
                new PaymentApiException('API error'),
                'Failed to fetch payment methods from Nexi API: API error',
            ],
            'generic exception' => [
                new \Exception('Unexpected error'),
                'Unexpected error fetching payment methods: Unexpected error',
            ],
        ];
    }

    private function createPaymentMethodsResult(array $methods): PaymentMethodsResult
    {
        return new PaymentMethodsResult($methods);
    }

    private function expectCreatePaymentApi(): void
    {
        $this->paymentApiProvider->expects($this->once())
            ->method('createPaymentApi')
            ->willReturn($this->paymentApi);
    }

    private function paymentMethodsRequestMatcher(?string $expectedCurrency): Constraint
    {
        return $this->callback(static fn ($request): bool => $request instanceof PaymentMethods
            && $request->getMerchantNumber() === null
            && $request->getCurrency() === $expectedCurrency
            && $request->getEnabled() === true
        );
    }
}
