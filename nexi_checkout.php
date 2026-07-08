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

use Nexi\Checkout\Hook\ActionEmailSendBeforeHandler;
use Nexi\Checkout\Hook\ActionListMailThemesHandler;
use Nexi\Checkout\Hook\AppScriptTagsRenderer;
use Nexi\Checkout\Hook\DisplayAdminOrderMainRenderer;
use Nexi\Checkout\Hook\NexiCheckoutScriptTagsRenderer;
use Nexi\Checkout\Hook\PaymentOptions;
use Nexi\Checkout\Install\ConfigurationInstaller;
use Nexi\Checkout\Install\CustomOrderStateInstaller;
use Nexi\Checkout\Install\DatabaseInstaller;
use Nexi\Checkout\Install\HookInstaller;
use Nexi\Checkout\Install\Installer;
use Nexi\Checkout\Uninstall\ConfigurationUninstaller;
use Nexi\Checkout\Uninstall\CustomOrderStateUninstaller;
use Nexi\Checkout\Uninstall\DatabaseUninstaller;
use Nexi\Checkout\Uninstall\Uninstaller;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class Nexi_Checkout extends PaymentModule
{
    public const MODULE_NAME = 'nexi_checkout';

    public const COMMERCE_PLATFORM_TAG = 'Prestashop';

    public function __construct()
    {
        $this->version = '2.0.0';
        $this->name = 'nexi_checkout';
        $this->module_key = '9df9540a2ed3dca1ac462cb88bc38ada';
        $this->displayName = $this->trans('Nexi Checkout', [], 'Modules.Nexicheckout.Install');
        $this->description = $this->trans('Allow you to accept different payment methods in Europe with a checkout built for conversion', [], 'Modules.Nexicheckout.Install');
        $this->tab = 'payments_gateways';
        $this->author = 'Nexi Checkout';
        $this->ps_versions_compliancy = ['min' => '8.2', 'max' => _PS_VERSION_];
        $this->confirmUninstall = $this->trans('This will permanently delete all Nexi data. Continue?', [], 'Modules.Nexicheckout.Install');

        parent::__construct();
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        $installer = new Installer(
            new DatabaseInstaller(),
            new ConfigurationInstaller(),
            new HookInstaller($this),
            new CustomOrderStateInstaller($this)
        );

        return $installer->install();
    }

    public function uninstall(): bool
    {
        if (!parent::uninstall()) {
            return false;
        }

        $uninstaller = new Uninstaller(
            new DatabaseUninstaller(),
            new ConfigurationUninstaller(),
            new CustomOrderStateUninstaller()
        );

        return $uninstaller->uninstall();
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function hookDisplayAdminOrderMain(array $params): string
    {
        $orderId = (int) ($params['id_order'] ?? 0);
        if ($orderId <= 0) {
            return '';
        }

        /** @var DisplayAdminOrderMainRenderer $renderer */
        $renderer = SymfonyContainer::getInstance()->get(DisplayAdminOrderMainRenderer::class);

        return $renderer->render($orderId);
    }

    public function hookDisplayBackOfficeFooter(): string
    {
        /** @var AppScriptTagsRenderer $renderer */
        $renderer = SymfonyContainer::getInstance()->get(AppScriptTagsRenderer::class);

        return $renderer->render($this->getPathUri(), $this->context->controller->controller_name);
    }

    public function hookDisplayPaymentTop(): string
    {
        /** @var NexiCheckoutScriptTagsRenderer $renderer */
        $renderer = $this->get(NexiCheckoutScriptTagsRenderer::class);

        return $renderer->render($this->getPathUri());
    }

    public function getContent(): void
    {
        $route = $this->get('router')->generate('nexi_checkout_configuration_form');
        Tools::redirectAdmin($route);
    }

    /**
     * @param array $params
     */
    public function hookPaymentOptions(array $params): array
    {
        /** @var Cart $cart */
        $cart = $params['cart'];

        if (false === Validate::isLoadedObject($cart)) {
            return [];
        }

        /** @var PaymentOptions $hook */
        $hook = $this->get(PaymentOptions::class);

        return $hook->build();
    }

    /**
     * This hook is used to add email twig layout to themes.
     *
     * @param array $params
     */
    public function hookActionListMailThemes(array $params): void
    {
        /** @var ActionListMailThemesHandler $hook */
        $hook = $this->get(ActionListMailThemesHandler::class);

        $hook->handle($params, $this->name);
    }

    /**
     * This hook is used to modify email template path when sending email using module template.
     *
     * @param array $params
     */
    public function hookActionEmailSendBefore(array &$params): void
    {
        /** @var ActionEmailSendBeforeHandler $hook */
        $hook = $this->get(ActionEmailSendBeforeHandler::class);

        $hook->handle($params, $this->name);
    }
}
