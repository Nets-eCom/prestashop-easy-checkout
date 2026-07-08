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
const NexiEmbeddedSplitCheckout = (function() {
    'use strict';

    /** @type {string|null} */
    let redirectUrl = null;

    function resetSplitContainer(container) {
        delete container.dataset.loaded;
        container._activeCheckout = null;
        const checkoutDiv = document.getElementById(container.dataset.checkoutContainerId);
        if (checkoutDiv) {
            checkoutDiv.setAttribute('hidden', '');
            checkoutDiv.innerHTML = '';
        }
        container.querySelector('.nexi-checkout-embedded-alert').setAttribute('hidden', '');
        container.querySelector('.nexi-checkout-split-loading').setAttribute('hidden', '');
    }

    async function loadSplitMethodCheckout(container) {
        const { method, createPaymentUrl, validateUrl, checkoutKey, language, checkoutContainerId } = container.dataset;
        const alertDiv = container.querySelector('.nexi-checkout-embedded-alert');
        const loadingDiv = container.querySelector('.nexi-checkout-split-loading');
        const checkoutDiv = document.getElementById(checkoutContainerId);

        alertDiv.setAttribute('hidden', '');
        checkoutDiv.setAttribute('hidden', '');
        loadingDiv.removeAttribute('hidden');

        try {
            const response = await fetch(createPaymentUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ method: method })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to initialize payment');
            }

            loadingDiv.setAttribute('hidden', '');
            checkoutDiv.removeAttribute('hidden');

            const checkout = new Dibs.Checkout({
                checkoutKey: checkoutKey,
                paymentId: data.paymentId,
                containerId: checkoutContainerId,
                language: language
            });

            container._activeCheckout = checkout;

            checkout.on('pay-initialized', async function() {
                if (container._activeCheckout !== checkout) {
                    return;
                }
                redirectUrl = await NexiEmbeddedCheckoutCommon.handlePaymentInitialized(
                    checkout,
                    validateUrl,
                    data.paymentId,
                    alertDiv
                );
            });

            checkout.on('payment-completed', function() {
                if (container._activeCheckout !== checkout) {
                    return;
                }
                NexiEmbeddedCheckoutCommon.handlePaymentCompleted(redirectUrl);
            });
        } catch (error) {
            loadingDiv.setAttribute('hidden', '');
            alertDiv.textContent = error.message;
            alertDiv.removeAttribute('hidden');
        }
    }

    function init() {
        document.querySelectorAll('input[name="payment-option"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const content = document.getElementById(radio.id + '-additional-information');
                if (!content) {
                    return;
                }
                const splitContainer = content.querySelector('.nexi-checkout-split-method-container');
                if (!splitContainer) {
                    return;
                }
                document.querySelectorAll('.nexi-checkout-split-method-container').forEach(function(c) {
                    if (c !== splitContainer) {
                        resetSplitContainer(c);
                    }
                });
                if (!splitContainer.dataset.loaded) {
                    splitContainer.dataset.loaded = 'true';
                    loadSplitMethodCheckout(splitContainer);
                }
            });
        });
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', function() {
    NexiEmbeddedSplitCheckout.init();
});
