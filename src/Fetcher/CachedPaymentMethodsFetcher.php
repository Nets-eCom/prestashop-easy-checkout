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

namespace Nexi\Checkout\Fetcher;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CachedPaymentMethodsFetcher implements PaymentMethodsFetcherInterface
{
    public const CACHE_KEY_PATTERN = \Nexi_Checkout::MODULE_NAME . '_available_payment_methods_%s_%s';

    public const CACHE_TTL = 60 * 60 * 3;

    private readonly \Cache $cache;

    public function __construct(
        private readonly PaymentMethodsFetcher $decorated,
        private readonly \Context $context,
    ) {
        $this->cache = \Cache::getInstance();
    }

    public function getAvailablePaymentMethods($currency = null): array
    {
        if ($currency !== null && !is_string($currency)) {
            throw new \InvalidArgumentException('Expected $currency to be a string or null.');
        }

        $cacheKey = sprintf(
            self::CACHE_KEY_PATTERN,
            $currency ?? 'ALL',
            $this->context->shop->id
        );

        $paymentMethods = $this->cache->get($cacheKey);

        if ($paymentMethods !== false && is_array($paymentMethods)) {
            return $paymentMethods;
        }

        $paymentMethods = $this->decorated->getAvailablePaymentMethods($currency);

        $this->cache->set($cacheKey, $paymentMethods, self::CACHE_TTL);

        return $paymentMethods;
    }

    public function clearCache(): void
    {
        $shopId = (int) $this->context->shop->id;
        $currencies = \Currency::getCurrencies(true, true);
        $currencyKeys = ['ALL'];

        foreach ($currencies as $currency) {
            $currencyKeys[] = $currency->iso_code;
        }

        foreach ($currencyKeys as $currencyIso) {
            $cacheKey = sprintf(
                self::CACHE_KEY_PATTERN,
                $currencyIso,
                $shopId,
            );

            if ($this->cache->exists($cacheKey)) {
                $this->cache->delete($cacheKey);
            }
        }
    }
}
