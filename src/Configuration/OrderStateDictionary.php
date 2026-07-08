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

namespace Nexi\Checkout\Configuration;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class OrderStateDictionary
{
    public const PAYMENT_NEW = 'NEXI_CHECKOUT_OS_PAYMENT_NEW';

    public const PAYMENT_ACCEPTED = 'NEXI_CHECKOUT_OS_PAYMENT_ACCEPTED';

    public const PAYMENT_CANCELED = 'NEXI_CHECKOUT_OS_PAYMENT_CANCELED';

    public const PAYMENT_REFUNDED_PARTIALLY = 'NEXI_CHECKOUT_OS_PAYMENT_REFUNDED_PARTIALLY';

    public const PAYMENT_CHARGED_PARTIALLY = 'NEXI_CHECKOUT_OS_PAYMENT_CHARGED_PARTIALLY';
}
