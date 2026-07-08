<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Provider;

use Nexi\Checkout\Fetcher\PaymentMethodsFetcherInterface;
use Nexi\Checkout\Service\Exception\PaymentMethodsNotAvailableException;
use Nexi\Checkout\Service\Exception\PaymentMethodsProviderException;
use Nexi\Checkout\Service\PaymentMethodsConfigurationService;
use Nexi\Checkout\Service\PaymentMethodsProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PaymentMethodsProviderTest extends TestCase
{
    public function testItProvidesPaymentMethodsWithNoSavedConfiguration(): void
    {
        $availableMethods = [
            ['value' => 'Cards', 'label' => 'Credit/Debit Cards'],
            ['value' => 'Paypal', 'label' => 'PayPal'],
        ];

        $fetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with('EUR')
            ->willReturn($availableMethods);

        $configurationService = $this->createMock(PaymentMethodsConfigurationService::class);
        $configurationService->expects($this->once())
            ->method('getAllPaymentMethods')
            ->willReturn([]);

        $sut = $this->createProvider(
            $fetcher,
            $configurationService,
            $this->createMock(LoggerInterface::class)
        );

        $result = $sut->provide('EUR');

        $this->assertCount(2, $result);
        $this->assertSame('Cards', $result[0]['name']);
        $this->assertSame('Credit/Debit Cards', $result[0]['label']);
        $this->assertFalse($result[0]['enabled']);
        $this->assertSame(0, $result[0]['order']);

        $this->assertSame('Paypal', $result[1]['name']);
        $this->assertSame('PayPal', $result[1]['label']);
        $this->assertFalse($result[1]['enabled']);
        $this->assertSame(1, $result[1]['order']);
    }

    public function testItProvidesPaymentMethodsWithSavedConfiguration(): void
    {
        $availableMethods = [
            ['value' => 'Cards', 'label' => 'Credit/Debit Cards'],
            ['value' => 'Paypal', 'label' => 'PayPal'],
        ];

        $savedConfiguration = [
            ['name' => 'Cards', 'enabled' => true, 'order' => 1],
            ['name' => 'Paypal', 'enabled' => false, 'order' => 0],
        ];

        $fetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with('EUR')
            ->willReturn($availableMethods);

        $configurationService = $this->createMock(PaymentMethodsConfigurationService::class);
        $configurationService->expects($this->once())
            ->method('getAllPaymentMethods')
            ->willReturn($savedConfiguration);

        $sut = $this->createProvider(
            $fetcher,
            $configurationService,
            $this->createMock(LoggerInterface::class)
        );

        $result = $sut->provide('EUR');

        $this->assertCount(2, $result);

        $this->assertSame('Paypal', $result[0]['name']);
        $this->assertSame('PayPal', $result[0]['label']);
        $this->assertFalse($result[0]['enabled']);
        $this->assertSame(0, $result[0]['order']);

        $this->assertSame('Cards', $result[1]['name']);
        $this->assertSame('Credit/Debit Cards', $result[1]['label']);
        $this->assertTrue($result[1]['enabled']);
        $this->assertSame(1, $result[1]['order']);
    }

    /**
     * @dataProvider currencyProvider
     */
    public function testItHandlesDifferentCurrencies(?string $currency): void
    {
        $availableMethods = [
            ['value' => 'Cards', 'label' => 'Credit/Debit Cards'],
        ];

        $fetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with($currency)
            ->willReturn($availableMethods);

        $configurationService = $this->createMock(PaymentMethodsConfigurationService::class);
        $configurationService->expects($this->once())
            ->method('getAllPaymentMethods')
            ->willReturn([]);

        $sut = $this->createProvider(
            $fetcher,
            $configurationService,
            $this->createMock(LoggerInterface::class)
        );

        $result = $sut->provide($currency);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertCount(1, $result);
    }

    public function testItMergesNewMethodsNotInConfiguration(): void
    {
        $availableMethods = [
            ['value' => 'Cards', 'label' => 'Credit/Debit Cards'],
            ['value' => 'Paypal', 'label' => 'PayPal'],
            ['value' => 'Applepay', 'label' => 'Apple Pay'],
        ];

        $savedConfiguration = [
            ['name' => 'Cards', 'enabled' => true, 'order' => 0],
            ['name' => 'Paypal', 'enabled' => false, 'order' => 1],
        ];

        $fetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with('EUR')
            ->willReturn($availableMethods);

        $configurationService = $this->createMock(PaymentMethodsConfigurationService::class);
        $configurationService->expects($this->once())
            ->method('getAllPaymentMethods')
            ->willReturn($savedConfiguration);

        $sut = $this->createProvider(
            $fetcher,
            $configurationService,
            $this->createMock(LoggerInterface::class)
        );

        $result = $sut->provide('EUR');

        $this->assertCount(3, $result);

        $applePayMethod = array_filter($result, fn (array $method): bool => $method['name'] === 'Applepay');
        $this->assertNotEmpty($applePayMethod);

        $applePayMethod = array_values($applePayMethod)[0];
        $this->assertSame('Apple Pay', $applePayMethod['label']);
        $this->assertFalse($applePayMethod['enabled']);
        $this->assertSame(2, $applePayMethod['order']);
    }

    public function testItSortsMethodsByOrder(): void
    {
        $availableMethods = [
            ['value' => 'Cards', 'label' => 'Credit/Debit Cards'],
            ['value' => 'Paypal', 'label' => 'PayPal'],
            ['value' => 'Applepay', 'label' => 'Apple Pay'],
        ];

        $savedConfiguration = [
            ['name' => 'Cards', 'enabled' => true, 'order' => 2],
            ['name' => 'Paypal', 'enabled' => true, 'order' => 0],
            ['name' => 'Applepay', 'enabled' => true, 'order' => 1],
        ];

        $fetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with('EUR')
            ->willReturn($availableMethods);

        $configurationService = $this->createMock(PaymentMethodsConfigurationService::class);
        $configurationService->expects($this->once())
            ->method('getAllPaymentMethods')
            ->willReturn($savedConfiguration);

        $sut = $this->createProvider(
            $fetcher,
            $configurationService,
            $this->createMock(LoggerInterface::class)
        );

        $result = $sut->provide('EUR');

        $this->assertCount(3, $result);

        $this->assertSame('Paypal', $result[0]['name']);
        $this->assertSame(0, $result[0]['order']);

        $this->assertSame('Applepay', $result[1]['name']);
        $this->assertSame(1, $result[1]['order']);

        $this->assertSame('Cards', $result[2]['name']);
        $this->assertSame(2, $result[2]['order']);
    }

    public function testItIgnoresConfigurationForMethodsNotInAPI(): void
    {
        $availableMethods = [
            ['value' => 'Cards', 'label' => 'Credit/Debit Cards'],
            ['value' => 'Paypal', 'label' => 'PayPal'],
        ];

        $savedConfiguration = [
            ['name' => 'Cards', 'enabled' => true, 'order' => 0],
            ['name' => 'Paypal', 'enabled' => false, 'order' => 1],
            ['name' => 'DEPRECATED_METHOD', 'enabled' => true, 'order' => 2],
        ];

        $fetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with('EUR')
            ->willReturn($availableMethods);

        $configurationService = $this->createMock(PaymentMethodsConfigurationService::class);
        $configurationService->expects($this->once())
            ->method('getAllPaymentMethods')
            ->willReturn($savedConfiguration);

        $sut = $this->createProvider(
            $fetcher,
            $configurationService,
            $this->createMock(LoggerInterface::class)
        );

        $result = $sut->provide('EUR');

        $this->assertCount(2, $result);

        $methodNames = array_column($result, 'name');
        $this->assertContains('Cards', $methodNames);
        $this->assertContains('Paypal', $methodNames);
        $this->assertNotContains('DEPRECATED_METHOD', $methodNames);
    }

    public function testItThrowsPaymentMethodsNotAvailableWhenEmptyArray(): void
    {
        $this->expectException(PaymentMethodsNotAvailableException::class);

        $fetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with('EUR')
            ->willReturn([]);

        $configurationService = $this->createMock(PaymentMethodsConfigurationService::class);
        $configurationService->expects($this->never())
            ->method('getAllPaymentMethods');

        $sut = $this->createProvider(
            $fetcher,
            $configurationService,
            $this->createMock(LoggerInterface::class)
        );

        $sut->provide('EUR');
    }

    public function testItThrowsPaymentMethodsProviderExceptionWhenFetcherFails(): void
    {
        $this->expectException(PaymentMethodsProviderException::class);
        $this->expectExceptionMessage('Failed to fetch payment methods: Connection failed');

        $fetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with('EUR')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to fetch payment methods: Connection failed'));

        $sut = $this->createProvider(
            $fetcher,
            $this->createMock(PaymentMethodsConfigurationService::class),
            $logger
        );

        $sut->provide('EUR');
    }

    public static function currencyProvider(): array
    {
        return [
            'EUR currency' => ['EUR'],
            'USD currency' => ['USD'],
            'GBP currency' => ['GBP'],
            'null currency' => [null],
        ];
    }

    private function createProvider(
        PaymentMethodsFetcherInterface $fetcher,
        PaymentMethodsConfigurationService $configurationService,
        LoggerInterface $logger,
    ): PaymentMethodsProvider {
        return new PaymentMethodsProvider(
            $fetcher,
            $configurationService,
            $logger
        );
    }
}
