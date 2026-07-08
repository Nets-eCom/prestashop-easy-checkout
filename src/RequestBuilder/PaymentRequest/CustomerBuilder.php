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

use NexiCheckout\Model\Request\Payment\Company;
use NexiCheckout\Model\Request\Payment\Consumer;
use NexiCheckout\Model\Request\Payment\PrivatePerson;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomerBuilder
{
    public function __construct(
        private readonly AddressBuilder $addressBuilder,
        private readonly PhoneNumberBuilder $phoneNumberBuilder,
    ) {
    }

    public function createFromCart(\Cart $cart): Consumer
    {
        $customer = new \Customer($cart->id_customer);
        $deliveryAddress = new \Address($cart->id_address_delivery);
        $invoiceAddress = new \Address($cart->id_address_invoice);

        $isCompany = $this->isCompany($invoiceAddress->company);

        return new Consumer(
            $customer->email,
            (string) $customer->id,
            $this->addressBuilder->createFromAddress($deliveryAddress),
            $this->addressBuilder->createFromAddress($invoiceAddress),
            $this->phoneNumberBuilder->createFromAddress($invoiceAddress),
            $isCompany ? null : new PrivatePerson($customer->firstname, $customer->lastname),
            $isCompany ? new Company(
                $invoiceAddress->company,
                $customer->firstname,
                $customer->lastname
            ) : null,
        );
    }

    private function isCompany(?string $name): bool
    {
        return $name !== null && trim($name) !== '' && $name !== '0';
    }
}
