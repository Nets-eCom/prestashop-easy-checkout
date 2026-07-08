<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Order\Provider;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Order\Provider\PaymentApiProvider;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

class PaymentApiProviderTest extends TestCase
{
    private const SHOP_ID = 1;

    private const SECRET_KEY = 'test-secret-key';

    private PaymentApiFactory&MockObject $apiFactoryMock;

    private ConfigurationProvider&MockObject $configurationProviderMock;

    private \Context $contextStub;

    private PaymentApiProvider $paymentApiProvider;

    protected function setUp(): void
    {
        $this->apiFactoryMock = $this->createMock(PaymentApiFactory::class);
        $this->configurationProviderMock = $this->createMock(ConfigurationProvider::class);

        $this->contextStub = \Context::getContext();
        $this->contextStub->shop = new \Shop();
        $this->contextStub->shop->id = self::SHOP_ID;

        $this->paymentApiProvider = new PaymentApiProvider(
            $this->apiFactoryMock,
            $this->configurationProviderMock,
            $this->contextStub
        );
    }

    public function testCreatePaymentApiReturnsCorrectInstance(): void
    {
        $isLiveMode = false;
        $paymentApi = $this->createMock(PaymentApi::class);

        $this->configurationProviderMock
            ->expects($this->once())
            ->method('getSecretKey')
            ->with($this->callback(fn (ShopConstraint $constraint): bool => $this->matchesCurrentShopConstraint($constraint)))
            ->willReturn(self::SECRET_KEY);

        $this->configurationProviderMock
            ->expects($this->once())
            ->method('isLiveMode')
            ->with($this->callback(fn (ShopConstraint $constraint): bool => $this->matchesCurrentShopConstraint($constraint)))
            ->willReturn($isLiveMode);

        $this->apiFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with(self::SECRET_KEY, $isLiveMode)
            ->willReturn($paymentApi);

        $result = $this->paymentApiProvider->createPaymentApi();

        $this->assertSame($paymentApi, $result);
    }

    private function matchesCurrentShopConstraint(ShopConstraint $constraint): bool
    {
        return $constraint->getShopId() === self::SHOP_ID;
    }
}
