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

namespace Nexi\Checkout\Controller\Admin;

use Nexi\Checkout\Fetcher\CachedPaymentMethodsFetcher;
use Nexi\Checkout\Service\Exception\PaymentMethodsNotAvailableException;
use Nexi\Checkout\Service\Exception\PaymentMethodsProviderException;
use Nexi\Checkout\Service\PaymentMethodsProvider;
use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class NexiConfigurationController extends PrestaShopAdminController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    // @TODO: Add security check
    public function configurationForm(
        Request $request,
        #[Autowire(service: 'prestashop.module.nexi_checkout.form.configuration_form_data_handler')]
        FormHandlerInterface $nexiConfigurationFormHandler,
        PaymentMethodsProvider $paymentMethodsProvider,
        CachedPaymentMethodsFetcher $cachedPaymentMethodsFetcher,
    ): Response {
        $configurationForm = $nexiConfigurationFormHandler->getForm();
        $configurationForm->handleRequest($request);

        if (!$configurationForm->isSubmitted() || !$configurationForm->isValid()) {
            return $this->renderConfigurationForm($configurationForm, $paymentMethodsProvider);
        }

        $cachedPaymentMethodsFetcher->clearCache();
        $errors = $nexiConfigurationFormHandler->save($configurationForm->getData());

        if (!empty($errors)) {
            $this->addFlashErrors($errors);

            return $this->renderConfigurationForm($configurationForm, $paymentMethodsProvider);
        }

        $this->addFlash('success', $this->trans('Successful update.', [], 'Admin.Notifications.Success'));

        return $this->redirectToRoute('nexi_checkout_configuration_form');
    }

    private function renderConfigurationForm(
        FormInterface $configurationForm,
        PaymentMethodsProvider $paymentMethodsProvider,
    ): Response {
        $formData = $configurationForm->getData();
        $isPaymentMethodSplittingEnabled = $formData['paymentMethodSplitting'] ?? false;

        $paymentMethods = [];

        if ($isPaymentMethodSplittingEnabled) {
            $currency = \Currency::getDefaultCurrency()->iso_code ??= null;

            try {
                $paymentMethods = $paymentMethodsProvider->provide($currency);
            } catch (PaymentMethodsNotAvailableException $exception) {
                $this->logger->error('Error fetching payment methods, no payment methods available.', ['exception' => $exception]);

                $this->addFlash(
                    'error',
                    $this->trans(
                        'No payment methods available. Please check your API credentials and reload the page.',
                        [],
                        'Modules.Nexicheckout.AdminConfiguration'
                    )
                );
            } catch (PaymentMethodsProviderException $exception) {
                $this->logger->error('Failed to fetch payment methods.', ['exception' => $exception]);

                $this->addFlash(
                    'error',
                    $this->trans(
                        'Failed to fetch payment methods.',
                        [],
                        'Modules.Nexicheckout.AdminConfiguration'
                    )
                );
            }
        }

        $formView = $configurationForm->createView();
        $formView['paymentMethods']->vars['payment_methods'] = $paymentMethods;
        $formView['paymentMethods']->vars['payment_method_splitting_enabled'] = $isPaymentMethodSplittingEnabled;

        return $this->render('@Modules/' . \Nexi_Checkout::MODULE_NAME . '/views/templates/admin/configuration/form.html.twig', [
            'configurationForm' => $formView,
        ]);
    }
}
