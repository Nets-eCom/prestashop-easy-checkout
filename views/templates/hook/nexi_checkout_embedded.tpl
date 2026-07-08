{**
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
 *}
{if $paymentError}
    <div class="alert alert-danger">
        {$paymentError|escape:'htmlall':'UTF-8'}
    </div>
{elseif $isSplit}
    <div class="nexi-checkout-split-method-container"
         data-method="{$methodName|escape:'htmlall':'UTF-8'}"
         data-checkout-key="{$checkoutKey|escape:'htmlall':'UTF-8'}"
         data-language="{$language|escape:'htmlall':'UTF-8'}"
         data-create-payment-url="{$createPaymentUrl|escape:'htmlall':'UTF-8'}"
         data-validate-url="{$validateUrl|escape:'htmlall':'UTF-8'}"
         data-checkout-container-id="{$checkoutContainerId|escape:'htmlall':'UTF-8'}">
        <div class="nexi-checkout-embedded-alert alert alert-danger" hidden></div>
        <div class="nexi-checkout-split-loading" hidden>
            <div class="spinner-border" role="status"></div>
        </div>
        <div id="{$checkoutContainerId|escape:'htmlall':'UTF-8'}" hidden></div>
    </div>
{else}
    <div id="nexi-checkout-embedded-alert" class="alert alert-danger" hidden></div>
    <div id="nexi-checkout-embedded-container"
         class="nexi-checkout-embedded"
         data-checkout-key="{$checkoutKey|escape:'htmlall':'UTF-8'}"
         data-payment-id="{$paymentId|escape:'htmlall':'UTF-8'}"
         data-language="{$language|escape:'htmlall':'UTF-8'}"
         data-validate-url="{$validateUrl|escape:'htmlall':'UTF-8'}">
        <div id="nexi-checkout"></div>
    </div>
{/if}
