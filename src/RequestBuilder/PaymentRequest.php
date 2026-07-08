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

namespace Nexi\Checkout\RequestBuilder;

use Nexi\Checkout\Helper\FormatHelper;
use Nexi\Checkout\RequestBuilder\PaymentRequest\CheckoutBuilder;
use Nexi\Checkout\RequestBuilder\PaymentRequest\ItemsBuilder;
use Nexi\Checkout\RequestBuilder\PaymentRequest\NotificationBuilder;
use NexiCheckout\Model\Request\Payment;
use NexiCheckout\Model\Request\Payment\MethodConfiguration;
use NexiCheckout\Model\Request\Shared\Order;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentRequest
{
    public function __construct(
        private readonly CheckoutBuilder $checkoutBuilder,
        private readonly ItemsBuilder $itemsBuilder,
        private readonly NotificationBuilder $notificationBuilder,
        private readonly FormatHelper $formatHelper,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function resolveMethodNames(string $methodName): array
    {
        $compoundMethods = [
            'GooglePay' => ['GooglePay', 'Card'],
            'ApplePay' => ['ApplePay', 'Card'],
        ];

        return $compoundMethods[$methodName] ?? [$methodName];
    }

    /**
     * @param list<MethodConfiguration> $paymentMethodsConfiguration
     */
    public function buildHosted(\Cart $cart, array $paymentMethodsConfiguration = []): Payment
    {
        $currency = new \Currency($cart->id_currency);

        return new Payment(
            new Order(
                $this->itemsBuilder->createFromCart($cart),
                $currency->iso_code,
                $this->formatHelper->priceToInt((float) $cart->getOrderTotal()),
                $cart->id ? (string) $cart->id : uniqid('cart_')
            ),
            $this->checkoutBuilder->createHosted($cart),
            $this->notificationBuilder->create(),
            myReference: $cart->secure_key,
            paymentMethodsConfiguration: $paymentMethodsConfiguration
        );
    }

    /**
     * @param list<MethodConfiguration> $paymentMethodsConfiguration
     */
    public function buildEmbedded(\Cart $cart, array $paymentMethodsConfiguration = []): Payment
    {
        $currency = new \Currency($cart->id_currency);

        return new Payment(
            new Order(
                $this->itemsBuilder->createFromCart($cart),
                $currency->iso_code,
                $this->formatHelper->priceToInt((float) $cart->getOrderTotal()),
                $cart->id ? (string) $cart->id : uniqid('cart_')
            ),
            $this->checkoutBuilder->createEmbedded($cart),
            $this->notificationBuilder->create(),
            myReference: $cart->secure_key,
            paymentMethodsConfiguration: $paymentMethodsConfiguration
        );
    }
}
