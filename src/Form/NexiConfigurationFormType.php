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

use Nexi\Checkout\Form\Extension\Core\Type\SecretType;
use Nexi\Checkout\Form\Type\SortablePaymentMethodsType;
use PrestaShopBundle\Form\Admin\Type\MultistoreConfigurationType;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

if (!defined('_PS_VERSION_')) {
    exit;
}

class NexiConfigurationFormType extends TranslatorAwareType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('liveSecretKey', SecretType::class, [
                'attr' => [
                    'class' => 'masksecret col-md-10 col-lg-8 p-1 h-25',
                    'placeholder' => '00000000000000000000000000000000',
                ],
                'required' => true,
                'label' => $this->trans('Live secret key', 'Modules.Nexicheckout.AdminConfiguration'),
                'help' => $this->trans('All keys can be found in: Nexi Easy Administration > Company > Integration', 'Modules.Nexicheckout.AdminConfiguration'),
                'constraints' => [new Length(['min' => 25]), new NotBlank()],
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::LIVE_SECRET_KEY,
            ])
            ->add('liveCheckoutKey', SecretType::class, [
                'attr' => [
                    'class' => 'masksecret col-md-10 col-lg-8 p-1 h-25',
                    'placeholder' => '00000000000000000000000000000000',
                ],
                'required' => true,
                'label' => $this->trans('Live checkout key', 'Modules.Nexicheckout.AdminConfiguration'),
                'constraints' => [new Length(['min' => 25]), new NotBlank()],
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::LIVE_CHECKOUT_KEY,
            ])
            ->add('testSecretKey', SecretType::class, [
                'attr' => [
                    'class' => 'masksecret col-md-10 col-lg-8 p-1 h-25',
                    'placeholder' => '00000000000000000000000000000000',
                ],
                'required' => true,
                'label' => $this->trans('Test secret key', 'Modules.Nexicheckout.AdminConfiguration'),
                'constraints' => [new Length(['min' => 25]), new NotBlank()],
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::TEST_SECRET_KEY,
            ])
            ->add('testCheckoutKey', SecretType::class, [
                'attr' => [
                    'class' => 'masksecret col-md-10 col-lg-8 p-1 h-25',
                    'placeholder' => '00000000000000000000000000000000',
                ],
                'required' => true,
                'label' => $this->trans('Test checkout key', 'Modules.Nexicheckout.AdminConfiguration'),
                'constraints' => [new Length(['min' => 25]), new NotBlank()],
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::TEST_CHECKOUT_KEY,
            ])
            ->add('autoCharge', SwitchType::class, [
                'label' => $this->trans('Enable auto-charge', 'Modules.Nexicheckout.AdminConfiguration'),
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::AUTO_CHARGE,
            ])
            ->add('liveMode', SwitchType::class, [
                'label' => $this->trans('Enable live mode', 'Modules.Nexicheckout.AdminConfiguration'),
                'help' => $this->trans('Choose wether you want to charge payments in test environment or live production mode', 'Modules.Nexicheckout.AdminConfiguration'),
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::LIVE_MODE,
            ])
            ->add('checkoutFlow', ChoiceType::class, [
                'label' => $this->trans('Checkout flow', 'Modules.Nexicheckout.AdminConfiguration'),
                'help' => $this->trans('Select the checkout flow type for processing payments', 'Modules.Nexicheckout.AdminConfiguration'),
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::CHECKOUT_FLOW,
                'choices' => [
                    $this->trans('Hosted', 'Modules.Nexicheckout.AdminConfiguration') => 0,
                    $this->trans('Embedded', 'Modules.Nexicheckout.AdminConfiguration') => 1,
                ],
            ])
            ->add('paymentMethodSplitting', SwitchType::class, [
                'label' => $this->trans('Payment method splitting', 'Modules.Nexicheckout.AdminConfiguration'),
                'help' => $this->trans('When enabled, each payment method appears as a separate option at checkout. When disabled, all methods are shown in a single Nexi Checkout option.', 'Modules.Nexicheckout.AdminConfiguration'),
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::PAYMENT_METHOD_SPLITTING,
                'required' => false,
            ])
            ->add('paymentMethods', SortablePaymentMethodsType::class, [
                'label' => $this->trans('Payment Methods', 'Modules.Nexicheckout.AdminConfiguration'),
                'help' => $this->trans('Configure which payment methods are available and their display order (only active when payment method splitting is enabled)', 'Modules.Nexicheckout.AdminConfiguration'),
                'required' => false,
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::PAYMENT_METHODS,
            ])
            ->add('termsUrl', UrlType::class, [
                'attr' => ['class' => 'col-md-10 col-lg-8 p-1 h-25'],
                'label' => $this->trans('Terms Url', 'Modules.Nexicheckout.AdminConfiguration'),
                'help' => $this->trans('Url to terms and conditions of the shop. Needs to start with https:// protocol.', 'Modules.Nexicheckout.AdminConfiguration'),
                'required' => true,
                'constraints' => [new Url(['protocols' => ['https']]), new NotBlank()],
                'default_protocol' => 'https',
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::TERMS_URL,
            ])
            ->add('merchantTermsUrl', UrlType::class, [
                'attr' => ['class' => 'col-md-10 col-lg-8 p-1 h-25'],
                'label' => $this->trans('Cookie Terms Url', 'Modules.Nexicheckout.AdminConfiguration'),
                'help' => $this->trans('Url to cookies policy of the shop. Needs to start with https:// protocol.', 'Modules.Nexicheckout.AdminConfiguration'),
                'required' => false,
                'constraints' => [new Url(['protocols' => ['https']])],
                'default_protocol' => 'https',
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::MERCHANT_TERMS_URL,
            ])
            ->add('webhookAuthorizationHeader', SecretType::class, [
                'attr' => ['class' => 'col-md-10 col-lg-6 p-1 h-25'],
                'required' => true,
                'constraints' => [new Length(['min' => 8, 'max' => 64]), new NotBlank()],
                'label' => $this->trans('Webhook Secret Code', 'Modules.Nexicheckout.AdminConfiguration'),
                'help' => 'Secret code used to authorize webhook calls',
                'multistore_configuration_key' => NexiConfigurationFormDataHandler::WEBHOOK_AUTHORIZATION_HEADER,
            ]);
    }

    /**
     * {@inheritdoc}
     *
     * @see PrestaShopBundle\Form\Extension\MultistoreConfigurationTypeExtension
     */
    public function getParent(): string
    {
        return MultistoreConfigurationType::class;
    }
}
