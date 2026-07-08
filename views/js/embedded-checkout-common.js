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
const NexiEmbeddedCheckoutCommon = (function() {
    'use strict';

    /**
     * @param {string} validateUrl
     * @param {string} paymentId
     *
     * @return {Promise<Object>}
     */
    async function validatePayment(validateUrl, paymentId) {
        const response = await fetch(validateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ paymentId: paymentId })
        });

        const data = await response.json();

        if (!response.ok) {
            throw data;
        }

        return data;
    }

    /**
     * @param {string|null} redirectUrl
     */
    function handlePaymentCompleted(redirectUrl) {
        if (!redirectUrl) {
            throw new Error('[NexiCheckout] Payment completed but no redirect URL available');
        }

        window.location.href = redirectUrl;
    }

    /**
     * @param {Object} checkout
     * @param {string} validateUrl
     * @param {string} paymentId
     * @param {HTMLElement|null} alertDiv
     *
     * @return {Promise<string|null>}
     */
    async function handlePaymentInitialized(checkout, validateUrl, paymentId, alertDiv) {
        try {
            const data = await validatePayment(validateUrl, paymentId);

            checkout.send('payment-order-finalized', true);

            return data.redirectUrl;
        } catch (error) {
            console.error('[NexiCheckout] Validation request failed:', error);
            checkout.send('payment-order-finalized', false);

            if (error.redirectUrl) {
                window.location.href = error.redirectUrl;
            }

            if (error.message && alertDiv) {
                alertDiv.textContent = error.message;
                alertDiv.removeAttribute('hidden');
                alertDiv.scrollIntoView();
                setTimeout(() => alertDiv.setAttribute('hidden', ''), 10000);
            }

            return null;
        }
    }

    return { handlePaymentCompleted, handlePaymentInitialized };
})();
