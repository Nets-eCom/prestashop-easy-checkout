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

namespace Nexi\Checkout\Service;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use PrestaShop\PrestaShop\Core\Domain\Configuration\ShopConfigurationInterface;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentMethodsConfigurationService
{
    public function __construct(
        private readonly ShopConfigurationInterface $configuration,
    ) {
    }

    /**
     * @return array<array{name: string, enabled: bool, order: int, label: string}>
     */
    public function getAllPaymentMethods($shopConstraint = null): array
    {
        if ($shopConstraint !== null && !$shopConstraint instanceof ShopConstraint) {
            throw new \InvalidArgumentException(sprintf('Expected instance of %s or null.', ShopConstraint::class));
        }

        $json = (string) $this->configuration->get(ConfigurationProvider::PAYMENT_METHODS, '[]', $shopConstraint);

        try {
            $methods = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($methods)) {
            return [];
        }

        usort($methods, fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $methods;
    }
}
