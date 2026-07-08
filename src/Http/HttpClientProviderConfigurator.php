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

namespace Nexi\Checkout\Http;

use NexiCheckout\Factory\Provider\HttpClientConfigurationProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

if (!defined('_PS_VERSION_')) {
    exit;
}

class HttpClientProviderConfigurator
{
    public function __construct(
        private readonly HttpClientConfigurationProvider $provider,
        #[Autowire(_PS_VERSION_)]
        private readonly string $prestashopVersion,
    ) {
    }

    public function configure(): void
    {
        $this->provider->setCommercePlatformTag($this->buildCommerceTag());
    }

    private function buildCommerceTag(): string
    {
        return \sprintf(
            '%s %s, %s, php%s',
            \Nexi_Checkout::COMMERCE_PLATFORM_TAG,
            $this->prestashopVersion,
            \Nexi_Checkout::MODULE_NAME,
            \PHP_VERSION
        );
    }
}
