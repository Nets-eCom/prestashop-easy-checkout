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
const NexiPaymentConfirmation = (function() {
    'use strict';

    const MODULE_NAME = 'nexi_checkout';
    const PAYMENT_OPTION_SELECTOR = 'input[type="radio"][name="payment-option"]';
    const CONDITIONS_SELECTOR = '#conditions-to-approve';
    const PAYMENT_CONFIRMATION_SELECTOR = '#payment-confirmation';

    /** @type {HTMLElement|null} */
    let conditionsContainer = null;

    /** @type {HTMLElement|null} */
    let paymentConfirmationContainer = null;

    /** @type {NodeListOf<HTMLInputElement>|null} */
    let paymentOptions = null;

    /**
     * @return {string|null}
     */
    function getSelectedPaymentModule() {
        const selectedOption = document.querySelector(PAYMENT_OPTION_SELECTOR + ':checked');

        return selectedOption ? selectedOption.dataset.moduleName : null;
    }

    /**
     * @return {boolean}
     */
    function isNexiCheckoutSelected() {
        return getSelectedPaymentModule() === MODULE_NAME;
    }

    function updateSectionsVisibility() {
        const hideElements = isNexiCheckoutSelected();

        if (conditionsContainer) {
            conditionsContainer.style.display = hideElements ? 'none' : '';
        }

        if (paymentConfirmationContainer) {
            paymentConfirmationContainer.style.display = hideElements ? 'none' : '';
        }
    }

    function bindEvents() {
        document.addEventListener('change', function(event) {
            if (event.target.matches(PAYMENT_OPTION_SELECTOR)) {
                updateSectionsVisibility();
            }
        });
    }

    /**
     * @return {boolean}
     */
    function init() {
        conditionsContainer = document.querySelector(CONDITIONS_SELECTOR);
        paymentConfirmationContainer = document.querySelector(PAYMENT_CONFIRMATION_SELECTOR);
        paymentOptions = document.querySelectorAll(PAYMENT_OPTION_SELECTOR);

        if (!paymentOptions.length) {
            return false;
        }

        bindEvents();
        updateSectionsVisibility();

        return true;
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', function() {
    NexiPaymentConfirmation.init();
});
