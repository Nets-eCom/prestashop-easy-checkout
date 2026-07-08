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

use Address as PrestaShopAddress;
use Nexi\Checkout\Helper\CountryIso2ToIso3Mapper;
use NexiCheckout\Model\Request\Payment\Address;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AddressBuilder
{
    public function createFromAddress(PrestaShopAddress $address): Address
    {
        $country = new \Country($address->id_country);

        $street = trim((string) $address->address1);
        $additionalAddress = trim((string) $address->address2);

        return new Address(
            $street,
            $additionalAddress ?: null,
            str_replace(' ', '', $address->postcode),
            $address->city,
            CountryIso2ToIso3Mapper::get($country->iso_code),
        );
    }
}
