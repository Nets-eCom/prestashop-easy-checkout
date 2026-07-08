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

namespace Nexi\Checkout\Uninstall;

use Nexi\Checkout\Form\NexiConfigurationFormDataHandler;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ConfigurationUninstaller implements UninstallStepInterface
{
    private const CONFIGURATION_KEYS = [
        NexiConfigurationFormDataHandler::LIVE_SECRET_KEY,
        NexiConfigurationFormDataHandler::LIVE_CHECKOUT_KEY,
        NexiConfigurationFormDataHandler::TEST_SECRET_KEY,
        NexiConfigurationFormDataHandler::TEST_CHECKOUT_KEY,
        NexiConfigurationFormDataHandler::AUTO_CHARGE,
        NexiConfigurationFormDataHandler::LIVE_MODE,
        NexiConfigurationFormDataHandler::CHECKOUT_FLOW,
        NexiConfigurationFormDataHandler::TERMS_URL,
        NexiConfigurationFormDataHandler::MERCHANT_TERMS_URL,
        NexiConfigurationFormDataHandler::WEBHOOK_AUTHORIZATION_HEADER,
    ];

    public function uninstall(): bool
    {
        foreach (self::CONFIGURATION_KEYS as $key) {
            if (!\Configuration::deleteByName($key)) {
                return false;
            }
        }

        return true;
    }
}
