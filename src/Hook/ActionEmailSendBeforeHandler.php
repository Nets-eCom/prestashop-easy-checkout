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

namespace Nexi\Checkout\Hook;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ActionEmailSendBeforeHandler
{
    public const HOOK = 'actionEmailSendBefore';

    private const MODULE_TEMPLATES = [
        'nexi_checkout_payment_cancelled',
        'nexi_checkout_payment_refunded_partially',
        'nexi_checkout_payment_charged_partially',
        'nexi_checkout_payment_new',
        'nexi_checkout_payment_accepted',
    ];

    public function handle(array &$params, string $moduleName): void
    {
        if (!in_array((string) ($params['template'] ?? ''), self::MODULE_TEMPLATES, true)) {
            return;
        }

        $params['templatePath'] = _PS_MODULE_DIR_ . $moduleName . '/mails/';
    }
}
