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

namespace Nexi\Checkout\Install;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DatabaseInstaller implements InstallStepInterface
{
    public const PAYMENT_DETAILS_TABLE = 'nexi_checkout_payment_details';

    public function install(): bool
    {
        $sql = [];
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::PAYMENT_DETAILS_TABLE . '` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT(10) UNSIGNED NULL,
            `payment_id` VARCHAR(64) NOT NULL,
            `order_data` JSON NOT NULL,
            `order_reference` VARCHAR(64) NULL,
            `embedded_cart_hash` VARCHAR(64) NULL,
            `charges` JSON NULL,
            `refunded_charges` JSON NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `nexi_details_order_id` (`order_id`),
            KEY `nexi_details_payment_id` (`payment_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (\Db::getInstance()->execute($query) === false) {
                // maybe add log with error
                return false;
            }
        }

        return true;
    }
}
