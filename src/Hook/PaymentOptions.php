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
use Nexi\Checkout\Hook\PaymentOption\EmbeddedPaymentOptionBuilder;
use Nexi\Checkout\Hook\PaymentOption\HostedPaymentOptionBuilder;
use Nexi\Checkout\Service\Exception\PaymentMethodsProviderException;
use Nexi\Checkout\Service\PaymentMethodsProvider;
use Nexi\Checkout\Traits\CartValidationTrait;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentOptions
{
    use CartValidationTrait;

    public const HOOK = 'paymentOptions';

    public function __construct(
        private readonly \Context $context,
        private readonly \Nexi_Checkout $module,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly EmbeddedPaymentOptionBuilder $embeddedPaymentOptionBuilder,
        private readonly HostedPaymentOptionBuilder $hostedPaymentOptionBuilder,
        private readonly PaymentMethodsProvider $paymentMethodsProvider,
    ) {
    }

    public function build(): array
    {
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);
        $isEmbedded = $this->canBuildEmbedded($shopConstraint);
        $isSplitting = $this->configurationProvider->isPaymentMethodSplittingEnabled($shopConstraint);

        if (!$isSplitting) {
            return $isEmbedded
                ? [$this->embeddedPaymentOptionBuilder->build()]
                : [$this->hostedPaymentOptionBuilder->build()];
        }

        $currency = new \Currency($this->context->cart->id_currency);

        try {
            $allMethods = $this->paymentMethodsProvider->provide($currency->iso_code);
        } catch (PaymentMethodsProviderException) {
            $allMethods = [];
        }

        $enabledMethods = array_values(array_filter(
            $allMethods,
            fn (array $method): bool => $method['enabled']
        ));

        if ($enabledMethods === []) {
            return $isEmbedded
                ? [$this->embeddedPaymentOptionBuilder->build()]
                : [$this->hostedPaymentOptionBuilder->build()];
        }

        if ($isEmbedded) {
            return array_map(
                fn (array $method) => $this->embeddedPaymentOptionBuilder->buildForMethod($method),
                $enabledMethods
            );
        }

        return array_map(
            fn (array $method) => $this->hostedPaymentOptionBuilder->buildForMethod($method),
            $enabledMethods
        );
    }

    public function canBuildEmbedded(ShopConstraint $shopConstraint): bool
    {
        if (!$this->module->active) {
            return false;
        }

        if (!$this->configurationProvider->isEmbeddedMode($shopConstraint)) {
            return false;
        }

        return $this->isCartReadyForPayment($this->context->cart);
    }
}
