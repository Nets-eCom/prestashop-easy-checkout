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

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use NexiCheckout\Model\Request\Payment\EmbeddedCheckout;
use NexiCheckout\Model\Request\Payment\HostedCheckout;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class CheckoutBuilder
{
    private \Link $link;

    public function __construct(
        private ConfigurationProvider $configurationProvider,
        private CustomerBuilder $customerBuilder,
        private \Context $context,
    ) {
        $this->link = $this->context->link;
    }

    public function createHosted(
        \Cart $cart,
    ): HostedCheckout {
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);

        return new HostedCheckout(
            $this->getReturnUrl(),
            $this->createCancelUrl(),
            $this->configurationProvider->getTermsUrl($shopConstraint),
            $this->configurationProvider->getMerchantTermsUrl($shopConstraint),
            $this->customerBuilder->createFromCart($cart),
            $this->configurationProvider->isAutoCharge($shopConstraint),
            true
        );
    }

    public function createEmbedded(\Cart $cart): EmbeddedCheckout
    {
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);

        return new EmbeddedCheckout(
            $this->getEmbeddedReturnUrl(),
            $this->configurationProvider->getTermsUrl($shopConstraint),
            $this->configurationProvider->getMerchantTermsUrl($shopConstraint),
            $this->customerBuilder->createFromCart($cart),
            $this->configurationProvider->isAutoCharge($shopConstraint),
            true
        );
    }

    private function createCancelUrl(): string
    {
        return $this->getReturnUrl();
    }

    private function getReturnUrl(): string
    {
        return $this->link->getModuleLink(
            'nexi_checkout',
            'return',
            [],
            true
        );
    }

    private function getEmbeddedReturnUrl(): string
    {
        return $this->link->getModuleLink(
            'nexi_checkout',
            'embedded_return',
            [],
            true
        );
    }
}
