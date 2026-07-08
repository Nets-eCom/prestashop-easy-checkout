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

use PrestaShop\PrestaShop\Core\Domain\Configuration\ShopConfigurationInterface;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ConfigurationProvider
{
    private const CONFIG_DOMAIN = 'NEXI_CHECKOUT_';

    public const LIVE_SECRET_KEY = self::CONFIG_DOMAIN . 'LIVE_SECRET_KEY';

    public const LIVE_CHECKOUT_KEY = self::CONFIG_DOMAIN . 'LIVE_CHECKOUT_KEY';

    public const TEST_SECRET_KEY = self::CONFIG_DOMAIN . 'TEST_SECRET_KEY';

    public const TEST_CHECKOUT_KEY = self::CONFIG_DOMAIN . 'TEST_CHECKOUT_KEY';

    public const LIVE_MODE = self::CONFIG_DOMAIN . 'LIVE_MODE';

    public const AUTO_CHARGE = self::CONFIG_DOMAIN . 'AUTO_CHARGE';

    public const TERMS_URL = self::CONFIG_DOMAIN . 'TERMS_URL';

    public const MERCHANT_TERMS_URL = self::CONFIG_DOMAIN . 'MERCHANT_TERMS_URL';

    public const WEBHOOK_AUTHORIZATION_HEADER = self::CONFIG_DOMAIN . 'WEBHOOK_AUTHORIZATION_HEADER';

    public const CHECKOUT_FLOW = self::CONFIG_DOMAIN . 'CHECKOUT_FLOW';

    public const PAYMENT_METHOD_SPLITTING = self::CONFIG_DOMAIN . 'PAYMENT_METHOD_SPLITTING';

    public const PAYMENT_METHODS = self::CONFIG_DOMAIN . 'PAYMENT_METHODS';

    public function __construct(private readonly ShopConfigurationInterface $configuration)
    {
    }

    public function getSecretKey($shopConstraint = null): string
    {
        $this->assertValidShopConstraint($shopConstraint);

        return $this->isLiveMode($shopConstraint) ?
            (string) $this->configuration->get(self::LIVE_SECRET_KEY, '', $shopConstraint) :
            (string) $this->configuration->get(self::TEST_SECRET_KEY, '', $shopConstraint);
    }

    public function getCheckoutKey($shopConstraint = null): string
    {
        $this->assertValidShopConstraint($shopConstraint);

        return $this->isLiveMode($shopConstraint) ?
            (string) $this->configuration->get(self::LIVE_CHECKOUT_KEY, '', $shopConstraint) :
            (string) $this->configuration->get(self::TEST_CHECKOUT_KEY, '', $shopConstraint);
    }

    public function getMerchantTermsUrl($shopConstraint = null): string
    {
        $this->assertValidShopConstraint($shopConstraint);

        return (string) $this->configuration->get(
            self::MERCHANT_TERMS_URL,
            '',
            $shopConstraint
        );
    }

    public function getTermsUrl($shopConstraint = null): string
    {
        $this->assertValidShopConstraint($shopConstraint);

        return (string) $this->configuration->get(
            self::TERMS_URL,
            '',
            $shopConstraint
        );
    }

    public function getWebhookAuthorizationHeader($shopConstraint = null): string
    {
        $this->assertValidShopConstraint($shopConstraint);

        return (string) $this->configuration->get(
            self::WEBHOOK_AUTHORIZATION_HEADER,
            '',
            $shopConstraint
        );
    }

    public function isLiveMode($shopConstraint = null): bool
    {
        $this->assertValidShopConstraint($shopConstraint);

        return (bool) $this->configuration->get(self::LIVE_MODE, false, $shopConstraint);
    }

    public function isAutoCharge($shopConstraint = null): bool
    {
        $this->assertValidShopConstraint($shopConstraint);

        return (bool) $this->configuration->get(self::AUTO_CHARGE, false, $shopConstraint);
    }

    public function isEmbeddedMode($shopConstraint = null): bool
    {
        $this->assertValidShopConstraint($shopConstraint);

        return (bool) $this->configuration->get(self::CHECKOUT_FLOW, 0, $shopConstraint);
    }

    public function isPaymentMethodSplittingEnabled($shopConstraint = null): bool
    {
        $this->assertValidShopConstraint($shopConstraint);

        return (bool) $this->configuration->get(self::PAYMENT_METHOD_SPLITTING, false, $shopConstraint);
    }

    private function assertValidShopConstraint($shopConstraint): void
    {
        if ($shopConstraint !== null && !$shopConstraint instanceof ShopConstraint) {
            throw new \InvalidArgumentException(sprintf('Expected instance of %s or null.', ShopConstraint::class));
        }
    }
}
