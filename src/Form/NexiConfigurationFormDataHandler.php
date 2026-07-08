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

namespace Nexi\Checkout\Form;

use Nexi\Checkout\Service\PaymentMethodsProvider;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Adapter\Shop\Context;
use PrestaShop\PrestaShop\Core\Configuration\AbstractMultistoreConfiguration;
use PrestaShop\PrestaShop\Core\Feature\FeatureInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class NexiConfigurationFormDataHandler extends AbstractMultistoreConfiguration
{
    public function __construct(Configuration $configuration, Context $shopContext, FeatureInterface $multistoreFeature, private readonly PaymentMethodsProvider $paymentMethodsProvider)
    {
        parent::__construct($configuration, $shopContext, $multistoreFeature);
    }

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

    /**
     * @var array<int, string>
     */
    private const CONFIGURATION_FIELDS = [
        'liveSecretKey',
        'liveCheckoutKey',
        'testSecretKey',
        'testCheckoutKey',
        'autoCharge',
        'liveMode',
        'checkoutFlow',
        'termsUrl',
        'merchantTermsUrl',
        'webhookAuthorizationHeader',
        'paymentMethodSplitting',
        'paymentMethods',
    ];

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): array
    {
        $return = [];
        $shopConstraint = $this->getShopConstraint();

        $return['liveSecretKey'] = $this->configuration->get(self::LIVE_SECRET_KEY, null, $shopConstraint);
        $return['liveCheckoutKey'] = $this->configuration->get(self::LIVE_CHECKOUT_KEY, null, $shopConstraint);
        $return['testSecretKey'] = $this->configuration->get(self::TEST_SECRET_KEY, null, $shopConstraint);
        $return['testCheckoutKey'] = $this->configuration->get(self::TEST_CHECKOUT_KEY, null, $shopConstraint);
        $return['autoCharge'] = $this->configuration->get(self::AUTO_CHARGE, false, $shopConstraint);
        $return['liveMode'] = $this->configuration->get(self::LIVE_MODE, false, $shopConstraint);
        $return['checkoutFlow'] = $this->configuration->get(self::CHECKOUT_FLOW, 0, $shopConstraint);
        $return['termsUrl'] = $this->configuration->get(self::TERMS_URL, null, $shopConstraint);
        $return['merchantTermsUrl'] = $this->configuration->get(self::MERCHANT_TERMS_URL, null, $shopConstraint);
        $return['webhookAuthorizationHeader'] = $this->configuration->get(self::WEBHOOK_AUTHORIZATION_HEADER, null, $shopConstraint);
        $return['paymentMethodSplitting'] = $this->configuration->get(self::PAYMENT_METHOD_SPLITTING, false, $shopConstraint);
        $return['paymentMethods'] = json_encode(value: $this->paymentMethodsProvider->provide(null));

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function updateConfiguration(array $configuration): array
    {
        $shopConstraint = $this->getShopConstraint();
        $this->updateConfigurationValue(self::LIVE_SECRET_KEY, 'liveSecretKey', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::LIVE_CHECKOUT_KEY, 'liveCheckoutKey', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::TEST_SECRET_KEY, 'testSecretKey', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::TEST_CHECKOUT_KEY, 'testCheckoutKey', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::AUTO_CHARGE, 'autoCharge', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::LIVE_MODE, 'liveMode', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::CHECKOUT_FLOW, 'checkoutFlow', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::TERMS_URL, 'termsUrl', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::MERCHANT_TERMS_URL, 'merchantTermsUrl', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::WEBHOOK_AUTHORIZATION_HEADER, 'webhookAuthorizationHeader', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::PAYMENT_METHOD_SPLITTING, 'paymentMethodSplitting', $configuration, $shopConstraint);
        $this->updateConfigurationValue(self::PAYMENT_METHODS, 'paymentMethods', $configuration, $shopConstraint);

        return [];
    }

    protected function buildResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined(self::CONFIGURATION_FIELDS);
        $resolver->setAllowedTypes('liveSecretKey', 'string');
        $resolver->setAllowedTypes('liveCheckoutKey', 'string');
        $resolver->setAllowedTypes('testSecretKey', 'string');
        $resolver->setAllowedTypes('testCheckoutKey', 'string');
        $resolver->setAllowedTypes('autoCharge', 'bool');
        $resolver->setAllowedTypes('liveMode', 'bool');
        $resolver->setAllowedTypes('checkoutFlow', 'int');
        $resolver->setAllowedTypes('termsUrl', 'string');
        $resolver->setAllowedValues('termsUrl', fn (string $value): bool => (bool) filter_var($value, FILTER_VALIDATE_URL));
        $resolver->setAllowedTypes('merchantTermsUrl', 'string');
        $resolver->setAllowedValues('merchantTermsUrl', fn (string $value): bool => (bool) filter_var($value, FILTER_VALIDATE_URL));
        $resolver->setAllowedTypes('webhookAuthorizationHeader', 'string');
        $resolver->setAllowedTypes('paymentMethodSplitting', 'bool');
        $resolver->setAllowedTypes('paymentMethods', 'string');

        return $resolver;
    }
}
