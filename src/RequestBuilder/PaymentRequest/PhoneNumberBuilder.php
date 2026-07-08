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

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use NexiCheckout\Model\Request\Payment\PhoneNumber;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PhoneNumberBuilder
{
    public function createFromAddress(\Address $address): ?PhoneNumber
    {
        $phoneNumber = $address->phone;

        if (empty($phoneNumber)) {
            $phoneNumber = $address->phone_mobile;
        }

        if (empty($phoneNumber)) {
            return null;
        }

        $country = new \Country($address->id_country);

        return $this->createPhoneNumber($phoneNumber, $country->iso_code);
    }

    /**
     * @todo move to builder so it can be mocked
     */
    protected function getPhoneNumberUtils(): PhoneNumberUtil
    {
        return PhoneNumberUtil::getInstance();
    }

    private function createPhoneNumber(string $number, string $countryIso): ?PhoneNumber
    {
        $phoneUtil = $this->getPhoneNumberUtils();
        try {
            $phoneNumberObject = $phoneUtil->parse(
                $number,
                $countryIso
            );
        } catch (NumberParseException) {
            // @TODO log error to investigate issue
            return null;
        }

        return new PhoneNumber(
            '+' . $phoneNumberObject->getCountryCode(),
            $phoneNumberObject->getNationalNumber()
        );
    }
}
