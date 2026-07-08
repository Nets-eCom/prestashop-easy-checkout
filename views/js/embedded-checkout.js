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
const NexiEmbeddedCheckout = (function() {
    'use strict';

    const CONTAINER_WRAPPER = 'nexi-checkout-embedded-container';
    const CONTAINER_ID = 'nexi-checkout';

    /**
     * @typedef {Object} Options
     * @property {string} checkoutKey
     * @property {string} paymentId
     * @property {string} language
     * @property {string} validateUrl
     */

    /** @type {string|null} */
    let redirectUrl = null;

    /**
     * @param {HTMLElement} container
     * @return {Options|null}
     */
    function getOptions(container) {
        const { checkoutKey, paymentId, language, validateUrl } = container.dataset;

        if (!checkoutKey || !paymentId) {
            console.error('[NexiEmbeddedCheckout] Missing required configuration: checkoutKey or paymentId');

            return null;
        }

        return { checkoutKey, paymentId, language, validateUrl };
    }

    /**
     * @param {Options} options
     * @return {Object|null}
     */
    function createCheckout(options) {
        if (typeof Dibs === 'undefined' || typeof Dibs.Checkout !== 'function') {
            console.error('[NexiEmbeddedCheckout] SDK not loaded');
            return null;
        }

        const checkout = new Dibs.Checkout({
            checkoutKey: options.checkoutKey,
            paymentId: options.paymentId,
            containerId: CONTAINER_ID,
            language: options.language
        });

        checkout.on('pay-initialized', async function() {
            redirectUrl = await NexiEmbeddedCheckoutCommon.handlePaymentInitialized(
                checkout,
                options.validateUrl,
                options.paymentId,
                document.getElementById('nexi-checkout-embedded-alert')
            );
        });

        checkout.on('payment-completed', function() {
            NexiEmbeddedCheckoutCommon.handlePaymentCompleted(redirectUrl);
        });

        return checkout;
    }

    function init() {
        const container = document.getElementById(CONTAINER_WRAPPER);

        if (!container) {
            return null;
        }

        const options = getOptions(container);

        if (!options) {
            return null;
        }

        return createCheckout(options);
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', function() {
    NexiEmbeddedCheckout.init();
});
