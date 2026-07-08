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

use Nexi\Checkout\Configuration\ConfigurationProvider;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class NexiCheckoutScriptTagsRenderer
{
    public const HOOK = 'displayPaymentTop';

    private const NEXI_CHECKOUT_SDK_TEST_URL = 'https://test.checkout.dibspayment.eu/v1/checkout.js?v=1';

    private const NEXI_CHECKOUT_SDK_LIVE_URL = 'https://checkout.dibspayment.eu/v1/checkout.js?v=1';

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly \Context $context,
    ) {
    }

    public function render(string $modulePath): string
    {
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);

        if (!$this->configurationProvider->isEmbeddedMode($shopConstraint)) {
            return '';
        }

        $sdkUrl = $this->configurationProvider->isLiveMode($shopConstraint)
            ? self::NEXI_CHECKOUT_SDK_LIVE_URL
            : self::NEXI_CHECKOUT_SDK_TEST_URL;

        $splitScript = $this->configurationProvider->isPaymentMethodSplittingEnabled($shopConstraint)
            ? sprintf('<script src="%sviews/js/embedded-checkout-split.js"></script>', $modulePath)
            : '';

        return <<<HTML
            <script src="{$sdkUrl}"></script>
            <script src="{$modulePath}views/js/embedded-checkout-common.js"></script>
            <script src="{$modulePath}views/js/embedded-checkout.js"></script>
            {$splitScript}
            <script src="{$modulePath}views/js/payment-confirmation.js"></script>
        HTML;
    }
}
