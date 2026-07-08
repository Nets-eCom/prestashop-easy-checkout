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
class PaymentMethodsManager {
    constructor(containerSelector, inputSelector) {
        this.container = document.querySelector(containerSelector);
        this.input = document.querySelector(inputSelector);
        this.methods = [];

        if (!this.container || !this.input) {
            console.error('PaymentMethodsManager: Required elements not found', {
                container: this.container,
                input: this.input
            });
            return;
        }

        this.init();
    }

    init() {
        this.loadConfiguration();

        if (this.methods.length === 0) {
            this.buildConfigurationFromDOM();
        }

        this.setupEventListeners();

        this.initSortable();
    }

    loadConfiguration() {
        try {
            const json = this.input.value;
            if (json && json !== '[]' && json !== '') {
                this.methods = JSON.parse(json);
            }
        } catch (e) {
            console.error('Failed to parse payment methods configuration:', e);
            this.methods = [];
        }
    }

    buildConfigurationFromDOM() {
        const items = this.container.querySelectorAll('.payment-method-item');

        items.forEach((item, index) => {
            const methodName = item.dataset.name;
            const label = item.querySelector('.method-name')?.textContent.trim();
            const enabled = item.querySelector('.method-toggle')?.checked || false;

            this.methods.push({
                name: methodName,
                label: label,
                enabled: enabled,
                order: index
            });
        });

        this.saveConfiguration();
    }

    saveConfiguration() {
        this.input.value = JSON.stringify(this.methods);
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    initSortable() {
        const $list = $('#payment-methods-sortable');
        if (!$list.length) {
            return;
        }

        $list.sortable({
            handle: '.drag-handle',
            opacity: 0.8,
            cursor: 'move',
            placeholder: 'payment-method-placeholder',
            distance: 5,
            tolerance: 'pointer',
            update: () => {
                this.updateOrder();
            }
        });
    }

    setupEventListeners() {
        this.container.addEventListener('change', (e) => {
            if (!e.target.classList.contains('method-toggle')) {
                return;
            }

            const index = parseInt(e.target.dataset.index);

            if (!this.methods[index]) {
                return;
            }

            this.methods[index].enabled = e.target.checked;

            const item = e.target.closest('.payment-method-item');
            item.classList.toggle('enabled', e.target.checked);
            item.classList.toggle('disabled', !e.target.checked);

            this.saveConfiguration();
        });
    }

    updateOrder() {
        const items = document.querySelectorAll('.payment-method-item');
        const newOrder = [];

        items.forEach((item, index) => {
            const methodName = item.dataset.name;
            const method = this.methods.find(m => m.name === methodName);

            if (!method) {
                return;
            }

            method.order = index;
            newOrder.push(method);
        });

        this.methods = newOrder;
        this.saveConfiguration();
    }
}

$(() => {
    const paymentMethodsSection = $('#payment-methods-container-wrapper');
    if (!paymentMethodsSection.length) {
        console.warn('Payment methods section not found');

        return;
    }

    const textarea = $('textarea.nexi-payment-methods-config');

    if (!textarea.length) {
        console.warn('Payment methods textarea not found');

        return;
    }

    window.paymentMethodsManager = new PaymentMethodsManager(
        '#payment-methods-container',
        'textarea.nexi-payment-methods-config'
    );
});
