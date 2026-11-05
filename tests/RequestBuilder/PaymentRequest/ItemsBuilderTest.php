<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Helper\FormatHelper;
use Nexi\Checkout\RequestBuilder\PaymentRequest\ItemsBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShopBundle\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;

class ItemsBuilderTest extends TestCase
{
    private ItemsBuilder $builder;

    protected function setUp(): void
    {
        $context = $this->createMock(\Context::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $context->method('getTranslator')->willReturn($translator);

        $this->builder = new ItemsBuilder(
            new FormatHelper(),
            $context,
            $this->createMock(LoggerInterface::class),
        );
    }

    #[DataProvider('referenceProvider')]
    public function testProductReference(int $idProduct, ?string $reference, string $expectedReference): void
    {
        $items = $this->builder->createFromCart($this->makeCart([
            'id_product' => $idProduct,
            'reference' => $reference,
            'name' => 'Test Product',
            'cart_quantity' => 1,
            'price' => 10.00,
            'total_wt' => 10.00,
            'total' => 10.00,
        ]));

        $this->assertCount(1, $items);
        $this->assertSame($expectedReference, $items[0]->getReference());
    }

    public static function referenceProvider(): array
    {
        return [
            'empty reference falls back to id_product' => [42, '', '42'],
            'null reference falls back to id_product' => [7, null, '7'],
            'non-empty reference kept as-is' => [99, 'SKU-001', 'SKU-001'],
        ];
    }

    private function makeCart(array $product): \Cart
    {
        $totalWt = (float) $product['total_wt'];

        $cart = $this->createMock(\Cart::class);
        $cart->gift = false;
        $cart->method('getProducts')->willReturn([$product]);
        $cart->method('getOrderTotal')->willReturnCallback(
            static fn (bool $withTaxes = true, int $type = \Cart::BOTH): float => $type === \Cart::ONLY_SHIPPING ? 0.0 : $totalWt
        );
        $cart->method('getDiscountSubtotalWithoutGifts')->willReturn(0.0);

        return $cart;
    }
}
