<?php

namespace Nexi\Checkout\Tests\Fetcher;

use Nexi\Checkout\Fetcher\CachedPaymentMethodsFetcher;
use Nexi\Checkout\Fetcher\PaymentMethodsFetcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CachedPaymentMethodsFetcherTest extends TestCase
{
    private const SHOP_ID = 1;

    private MockObject $cache;

    private MockObject $context;

    private MockObject $decorated;

    private CachedPaymentMethodsFetcher $fetcher;

    protected array $currenciesResponse = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->createMock(\Cache::class);
        \Cache::$instance = $this->cache;
        $this->context = $this->createMock(\Context::class);
        $this->decorated = $this->createMock(PaymentMethodsFetcher::class);

        $shop = new \stdClass();
        $shop->id = self::SHOP_ID;

        $this->context->shop = $shop;

        \Currency::$mock = new class($this) {
            public function __construct(private readonly CachedPaymentMethodsFetcherTest $testCase)
            {
            }

            public function getCurrencies(): array
            {
                return $this->testCase->getCurrenciesResponse();
            }
        };

        $this->fetcher = new CachedPaymentMethodsFetcher($this->decorated, $this->context);
    }

    public function getCurrenciesResponse(): array
    {
        return $this->currenciesResponse;
    }

    protected function tearDown(): void
    {
        \Currency::$mock = null;
        \Cache::$instance = null;
        $this->currenciesResponse = [];

        parent::tearDown();
    }

    public function testGetAvailablePaymentMethodsReturnsCachedData(): void
    {
        $cacheKey = $this->createCacheKey();
        $cachedPaymentMethods = ['visa', 'mastercard'];

        $this->cache->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn($cachedPaymentMethods);
        $this->cache->expects($this->never())->method('set');
        $this->decorated->expects($this->never())->method('getAvailablePaymentMethods');

        $result = $this->fetcher->getAvailablePaymentMethods();

        $this->assertSame($cachedPaymentMethods, $result);
    }

    public function testGetAvailablePaymentMethodsIgnoresInvalidCachedValue(): void
    {
        $cacheKey = $this->createCacheKey('SEK');
        $fetchedPaymentMethods = ['swish'];

        $this->cache->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn('invalid-cache-payload');

        $this->decorated->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with('SEK')
            ->willReturn($fetchedPaymentMethods);

        $this->cache->expects($this->once())
            ->method('set')
            ->with($cacheKey, $fetchedPaymentMethods, CachedPaymentMethodsFetcher::CACHE_TTL);

        $result = $this->fetcher->getAvailablePaymentMethods('SEK');

        $this->assertSame($fetchedPaymentMethods, $result);
    }

    public function testGetAvailablePaymentMethodsFetchesAndCachesDataWhenNotCached(): void
    {
        $cacheKey = $this->createCacheKey('DK');
        $fetchedPaymentMethods = ['method1', 'method2'];
        $currency = 'DK';

        $this->cache->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn(false);

        $this->decorated->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with($currency)
            ->willReturn($fetchedPaymentMethods);

        $this->cache->expects($this->once())
            ->method('set')
            ->with($cacheKey, $fetchedPaymentMethods, CachedPaymentMethodsFetcher::CACHE_TTL);

        $result = $this->fetcher->getAvailablePaymentMethods($currency);

        $this->assertSame($fetchedPaymentMethods, $result);
    }

    public function testGetAvailablePaymentMethodsHandlesNullCurrency(): void
    {
        $cacheKey = $this->createCacheKey();
        $fetchedPaymentMethods = ['card', 'klarna'];

        $this->cache->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn(false);

        $this->decorated->expects($this->once())
            ->method('getAvailablePaymentMethods')
            ->with(null)
            ->willReturn($fetchedPaymentMethods);

        $this->cache->expects($this->once())
            ->method('set')
            ->with($cacheKey, $fetchedPaymentMethods, CachedPaymentMethodsFetcher::CACHE_TTL);

        $result = $this->fetcher->getAvailablePaymentMethods();

        $this->assertSame($fetchedPaymentMethods, $result);
    }

    public function testClearCacheRemovesAllKeys(): void
    {
        $this->mockCurrencies(['USD', 'EUR']);
        $checkedKeys = [];
        $deletedKeys = [];

        $this->cache->expects($this->exactly(3))
            ->method('exists')
            ->willReturnCallback(function (string $key) use (&$checkedKeys): bool {
                $checkedKeys[] = $key;

                return true;
            });

        $this->cache->expects($this->exactly(3))
            ->method('delete')
            ->willReturnCallback(function (string $key) use (&$deletedKeys): bool {
                $deletedKeys[] = $key;

                return true;
            });

        $expectedKeys = [
            $this->createCacheKey(),
            $this->createCacheKey('USD'),
            $this->createCacheKey('EUR'),
        ];

        $this->fetcher->clearCache();

        $this->assertSame($expectedKeys, $checkedKeys);
        $this->assertSame($expectedKeys, $deletedKeys);
    }

    public function testClearCacheSkipsNonexistentKeys(): void
    {
        $this->mockCurrencies(['GBP']);
        $checkedKeys = [];

        $this->cache->expects($this->exactly(2))
            ->method('exists')
            ->willReturnCallback(function (string $key) use (&$checkedKeys): bool {
                $checkedKeys[] = $key;

                return false;
            });

        $this->cache->expects($this->never())->method('delete');

        $this->fetcher->clearCache();

        $this->assertSame([
            $this->createCacheKey(),
            $this->createCacheKey('GBP'),
        ], $checkedKeys);
    }

    public function testClearCacheDeletesOnlyExistingKeys(): void
    {
        $this->mockCurrencies(['USD', 'EUR']);
        $checkedKeys = [];
        $deletedKeys = [];
        $existingKeys = [
            $this->createCacheKey(),
            $this->createCacheKey('EUR'),
        ];

        $this->cache->expects($this->exactly(3))
            ->method('exists')
            ->willReturnCallback(function (string $key) use (&$checkedKeys, $existingKeys): bool {
                $checkedKeys[] = $key;

                return in_array($key, $existingKeys, true);
            });

        $this->cache->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (string $key) use (&$deletedKeys): bool {
                $deletedKeys[] = $key;

                return true;
            });

        $this->fetcher->clearCache();

        $this->assertSame([
            $this->createCacheKey(),
            $this->createCacheKey('USD'),
            $this->createCacheKey('EUR'),
        ], $checkedKeys);
        $this->assertSame($existingKeys, $deletedKeys);
    }

    private function createCacheKey(?string $currency = null): string
    {
        return sprintf(
            CachedPaymentMethodsFetcher::CACHE_KEY_PATTERN,
            $currency ?? 'ALL',
            self::SHOP_ID,
        );
    }

    /**
     * @param list<string> $isoCodes
     */
    private function mockCurrencies(array $isoCodes): void
    {
        $this->currenciesResponse = array_map(
            static fn (string $isoCode): \Currency => new \Currency(['iso_code' => $isoCode]),
            $isoCodes,
        );
    }
}
