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

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Administration\Model\Item as ModelItem;
use Nexi\Checkout\Helper\FormatHelper;
use NexiCheckout\Model\Request\Item;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ItemsBuilder
{
    private const ITEM_UNIT = 'pcs';

    private const SHIPPING_REF = 'shipping';

    public function __construct(
        private readonly FormatHelper $helper,
        private readonly \Context $context,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<Item>
     */
    public function createFromCart(\Cart $cart): array
    {
        $items = [];

        $products = $cart->getProducts(true);

        foreach ($products as $product) {
            $items[] = $this->createFromCartProduct($product);
        }

        $shippingCost = (float) $cart->getOrderTotal(true, \Cart::ONLY_SHIPPING);
        if ($shippingCost > 0) {
            $items[] = $this->createShippingItem($cart, $shippingCost);
        }

        if ($cart->gift) {
            $items[] = $this->createGiftWrappingItem($cart);
        }

        $discountValue = $cart->getDiscountSubtotalWithoutGifts();
        if ($discountValue < 0) {
            $items[] = $this->createDiscountItem($discountValue);
        }

        $cartTotal = $this->priceToInt((float) $cart->getOrderTotal());
        $itemsSum = array_sum(array_map(fn (Item $item): int => $item->getGrossTotalAmount(), $items));
        $diff = $cartTotal - $itemsSum;

        if ($diff !== 0) {
            $this->logger->info('Rounding item added to payment request', [
                'cartId' => $cart->id,
                'cartTotal' => $cartTotal,
                'itemsSum' => $itemsSum,
                'diff' => $diff,
            ]);

            $items[] = $this->createRoundingItem($diff);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function createFromCartProduct(array $product): Item
    {
        $quantity = (int) $product['cart_quantity'];
        $priceWithoutTax = (float) $product['price'];
        $totalWithTax = (float) $product['total_wt'];
        $totalWithoutTax = (float) $product['total'];

        $unitPrice = $this->priceToInt($priceWithoutTax);
        $grossTotalAmount = $this->priceToInt($totalWithTax);
        $netTotalAmount = $this->priceToInt($totalWithoutTax);
        $taxAmount = $grossTotalAmount - $netTotalAmount;

        $taxRate = $unitPrice > 0
            ? $this->priceToInt($taxAmount / $unitPrice)
            : 0;

        $reference = $product['reference'] ?? (string) $product['id_product'];

        return new Item(
            $this->sanitize($product['name']),
            $quantity,
            self::ITEM_UNIT,
            $unitPrice,
            $grossTotalAmount,
            $netTotalAmount,
            substr($reference, 0, 128),
            $taxRate,
            $taxAmount
        );
    }

    private function createFromOrderProduct(array $product, ModelItem $chargedProduct): Item
    {
        $quantity = $chargedProduct->getQuantity();
        $priceWithoutTax = (float) $product['price'];
        $totalWithTax = $chargedProduct->getAmount();
        $withoutTax = (float) $product['unit_price_tax_excl'];
        $totalWithoutTax = $withoutTax * $chargedProduct->getQuantity();

        $unitPrice = $this->priceToInt($priceWithoutTax);
        $grossTotalAmount = $this->priceToInt($totalWithTax);
        $netTotalAmount = $this->priceToInt($totalWithoutTax);
        $taxAmount = $grossTotalAmount - $netTotalAmount;

        $taxRate = $unitPrice > 0
            ? $this->priceToInt($taxAmount / $unitPrice)
            : 0;

        $reference = $product['reference'] ?? (string) $product['id_product'];

        return new Item(
            $this->sanitize($product['product_name']),
            $quantity,
            self::ITEM_UNIT,
            $unitPrice,
            $grossTotalAmount,
            $netTotalAmount,
            substr($reference, 0, 128),
            $taxRate,
            $taxAmount
        );
    }

    private function createFromShipping(ModelItem $chargedProduct): Item
    {
        $chargeAmount = $this->priceToInt($chargedProduct->getAmount());

        return new Item(
            self::SHIPPING_REF,
            1,
            self::ITEM_UNIT,
            $chargeAmount,
            $chargeAmount,
            $chargeAmount,
            self::SHIPPING_REF,
        );
    }

    public function createUnrelatedPartialChargeItem(\Order $order, float $amount, ?array $charges): Item
    {
        $chargeAmount = $this->priceToInt($amount);
        $count = is_array($charges) ? count($charges) : 0;
        $reference = \sprintf('charge %d', $count + 1);
        $name = \sprintf('order %s %s', $order->reference, $reference);

        return new Item(
            $this->sanitize($name),
            1,
            self::ITEM_UNIT,
            $chargeAmount,
            $chargeAmount,
            $chargeAmount,
            substr($reference, 0, 128),
        );
    }

    public function createForCharge(ChargeData $chargeData, array $cartItemsArray): array
    {
        $chargeItems = $chargeData->getItems();
        $returnItems = [];
        foreach ($chargeItems as $chargeItem) {
            if ($chargeItem->getReference() === self::SHIPPING_REF) {
                $returnItems[] = $this->createFromShipping($chargeItem);
                continue;
            }

            $item = $this->findItemByReference($cartItemsArray, $chargeItem->getReference());
            $returnItems[] = $this->createFromOrderProduct($item, $chargeItem);
        }

        return $returnItems;
    }

    private function findItemByReference(array $cartItemsArray, string $reference): array
    {
        foreach ($cartItemsArray as $item) {
            if ($item['reference'] === $reference) {
                return $item;
            }
        }

        throw new \LogicException('Item not found');
    }

    private function createShippingItem(\Cart $cart, float $shippingCost): Item
    {
        $carrier = new \Carrier($cart->id_carrier);
        $shippingName = trim((string) $carrier->name);
        $shippingName = $shippingName !== '' ? $shippingName : 'Shipping';

        $shippingWithoutTax = (float) $cart->getOrderTotal(false, \Cart::ONLY_SHIPPING);

        $unitPrice = $this->priceToInt($shippingWithoutTax);
        $grossTotalAmount = $this->priceToInt($shippingCost);
        $netTotalAmount = $unitPrice;
        $taxAmount = $grossTotalAmount - $netTotalAmount;

        $taxRate = $unitPrice > 0
            ? $this->priceToInt($taxAmount / $unitPrice)
            : 0;

        return new Item(
            $shippingName,
            1,
            self::ITEM_UNIT,
            $unitPrice,
            $grossTotalAmount,
            $netTotalAmount,
            'shipping',
            $taxRate,
            $taxAmount
        );
    }

    private function createGiftWrappingItem(\Cart $cart): Item
    {
        $gross = $this->priceToInt($cart->getGiftWrappingPrice());
        $net = $this->priceToInt($cart->getGiftWrappingPrice(false));
        $taxAmount = $gross - $net;

        $taxRate = $net > 0
            ? $this->priceToInt($taxAmount / $net)
            : 0;

        return new Item(
            $this->translate('Gift wrapping', [], 'Shop.Theme.Checkout'),
            1,
            self::ITEM_UNIT,
            $net,
            $gross,
            $net,
            'gift_wrapping',
            $taxRate,
            $taxAmount
        );
    }

    private function createRoundingItem(int $amount): Item
    {
        return new Item('Rounding', 1, self::ITEM_UNIT, $amount, $amount, $amount, 'rounding');
    }

    private function createDiscountItem(float $discountSubtotal): Item
    {
        $amount = $this->priceToInt($discountSubtotal);

        return new Item(
            $this->translate('Discount', [], 'Shop.Navigation'),
            1,
            self::ITEM_UNIT,
            -$amount,
            -$amount,
            -$amount,
            'discount',
            0,
            0
        );
    }

    private function priceToInt(float $price): int
    {
        return $this->helper->priceToInt($price);
    }

    private function sanitize(string $label): string
    {
        return $this->helper->sanitizeString($label);
    }

    private function translate(string $key, array $params, string $domain): string
    {
        return $this->context->getTranslator()->trans($key, $params, $domain);
    }
}
