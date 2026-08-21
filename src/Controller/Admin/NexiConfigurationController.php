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
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class NexiConfigurationController extends ModuleAdminController
{
    public function __construct(
        private readonly FormHandlerInterface $nexiConfigurationFormHandler,
        private readonly PaymentMethodsProvider $paymentMethodsProvider,
        private readonly CachedPaymentMethodsFetcher $cachedPaymentMethodsFetcher,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    // @TODO: Add security check
    public function configurationForm(
        Request $request,
    ): Response {
        $configurationForm = $this->nexiConfigurationFormHandler->getForm();
        $configurationForm->handleRequest($request);

        if (!$configurationForm->isSubmitted() || !$configurationForm->isValid()) {
            return $this->renderConfigurationForm($configurationForm);
        }

        $this->cachedPaymentMethodsFetcher->clearCache();
        $errors = $this->nexiConfigurationFormHandler->save($configurationForm->getData());

        if (!empty($errors)) {
            $this->addFlashErrors($errors);

            return $this->renderConfigurationForm($configurationForm);
        }

        $this->addFlash('success', $this->translator->trans('Successful update.', [], 'Modules.Nexicheckout.AdminConfiguration'));

        return $this->redirectToRoute('nexi_checkout_configuration_form');
    }

    private function renderConfigurationForm(
        FormInterface $configurationForm,
    ): Response {
        $formData = $configurationForm->getData();
        $isPaymentMethodSplittingEnabled = $formData['paymentMethodSplitting'] ?? false;

        $paymentMethods = [];

        if ($isPaymentMethodSplittingEnabled) {
            $currency = \Currency::getDefaultCurrency()->iso_code ??= null;

            try {
                $paymentMethods = $this->paymentMethodsProvider->provide($currency);
            } catch (PaymentMethodsNotAvailableException $exception) {
                $this->logger->error('Error fetching payment methods, no payment methods available.', ['exception' => $exception]);

                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'No payment methods available. Please check your API credentials and reload the page.',
                        [],
                        'Modules.Nexicheckout.AdminConfiguration'
                    )
                );
            } catch (PaymentMethodsProviderException $exception) {
                $this->logger->error('Failed to fetch payment methods.', ['exception' => $exception]);

                $this->addFlash(
                    'error',
                    $this->translator->trans(
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
