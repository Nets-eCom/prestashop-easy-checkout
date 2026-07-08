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

use PrestaShop\PrestaShop\Core\MailTemplate\Layout\Layout;
use PrestaShop\PrestaShop\Core\MailTemplate\Theme;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCatalogInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCollection;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ActionListMailThemesHandler
{
    public const HOOK = ThemeCatalogInterface::LIST_MAIL_THEMES_HOOK;

    public function handle(array $hookParams, string $moduleName): void
    {
        if (!isset($hookParams['mailThemes']) || !$hookParams['mailThemes'] instanceof ThemeCollection) {
            return;
        }

        /** @var Theme $theme */
        foreach ($hookParams['mailThemes'] as $theme) {
            if (!in_array($theme->getName(), ['classic', 'modern'], true)) {
                continue;
            }

            $theme->getLayouts()->add(new Layout(
                'nexi_checkout_payment_cancelled',
                '@Modules/nexi_checkout/mails/layouts/nexi_checkout_payment_cancelled.html.twig',
                '',
                $moduleName
            ));

            $theme->getLayouts()->add(new Layout(
                'nexi_checkout_payment_charged_partially',
                '@Modules/nexi_checkout/mails/layouts/nexi_checkout_payment_charged_partially.html.twig',
                '',
                $moduleName
            ));

            $theme->getLayouts()->add(new Layout(
                'nexi_checkout_payment_refunded_partially',
                '@Modules/nexi_checkout/mails/layouts/nexi_checkout_payment_refunded_partially.html.twig',
                '',
                $moduleName
            ));
        }
    }
}
