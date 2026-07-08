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

namespace Nexi\Checkout\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CartSignature
{
    public function generateHashSignatureByCart(\Cart $cart): string
    {
        $shippingMethod = null;
        $shippingAddress = null;
        if (!$cart->isVirtualCart()) {
            $shippingAddress = new \Address($cart->id_address_delivery);
            $carrier = new \Carrier($cart->id_carrier);
            $shippingMethod = $carrier->name;
        }

        $billingAddress = new \Address($cart->id_address_invoice);
        $info = [
            'currency' => (new \Currency($cart->id_currency))->iso_code,
            'shipping_method' => $shippingMethod,
            'shipping_country' => $shippingAddress?->id_country,
            'shipping_postcode' => $shippingAddress?->postcode,
            'billing_country' => $billingAddress->id_country,
            'billing_postcode' => $billingAddress->postcode,
            'total' => sprintf('%.2f', round($cart->getOrderTotal(), 2)),
            'items' => [],
        ];

        foreach ($cart->getProducts(true) as $item) {
            $info['items'][$item['reference'] ?? (string) $item['id_product']] = sprintf('%.2f', round($item['total_wt'], 2));
        }

        ksort($info['items']);

        return hash('sha256', json_encode($info, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
