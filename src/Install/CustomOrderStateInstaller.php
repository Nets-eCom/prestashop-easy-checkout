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

namespace Nexi\Checkout\Install;

use Nexi\Checkout\Configuration\OrderStateDictionary;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomOrderStateInstaller implements InstallStepInterface
{
    public const ORDER_STATES = [
        [
            'name' => 'Awaiting Payment (Nexi Checkout)',
            'configKey' => OrderStateDictionary::PAYMENT_NEW,
            'color' => '#34209E',
            'sendEmail' => false,
            'invoice' => false,
            'logable' => true,
            'shipped' => false,
            'paid' => false,
            'delivery' => false,
            'hidden' => false,
            'unremovable' => true,
            'deleted' => false,
            'template' => 'nexi_checkout_payment_new',
        ],
        [
            'name' => 'Payment Accepted (Nexi Checkout)',
            'configKey' => OrderStateDictionary::PAYMENT_ACCEPTED,
            'color' => '#4169E1',
            'sendEmail' => false,
            'invoice' => false,
            'logable' => true,
            'shipped' => false,
            'paid' => false,
            'delivery' => false,
            'hidden' => false,
            'unremovable' => true,
            'deleted' => false,
            'template' => 'nexi_checkout_payment_accepted',
        ],
        [
            'name' => 'Payment Canceled (Nexi Checkout)',
            'configKey' => OrderStateDictionary::PAYMENT_CANCELED,
            'color' => '#DC143C',
            'sendEmail' => true,
            'invoice' => false,
            'logable' => false,
            'shipped' => false,
            'paid' => false,
            'delivery' => false,
            'hidden' => false,
            'unremovable' => true,
            'deleted' => false,
            'template' => 'nexi_checkout_payment_cancelled',
        ],
        [
            'name' => 'Refunded partially (Nexi Checkout)',
            'configKey' => OrderStateDictionary::PAYMENT_REFUNDED_PARTIALLY,
            'color' => '#01B887',
            'sendEmail' => true,
            'invoice' => false,
            'logable' => true,
            'shipped' => false,
            'paid' => false,
            'delivery' => false,
            'hidden' => false,
            'unremovable' => true,
            'deleted' => false,
            'template' => 'nexi_checkout_payment_refunded_partially',
        ],
        [
            'name' => 'Payment Paid Partially (Nexi Checkout)',
            'configKey' => OrderStateDictionary::PAYMENT_CHARGED_PARTIALLY,
            'color' => '#3498D8',
            'sendEmail' => true,
            'invoice' => false,
            'logable' => true,
            'shipped' => false,
            'paid' => true,
            'delivery' => false,
            'hidden' => false,
            'unremovable' => true,
            'deleted' => false,
            'template' => 'nexi_checkout_payment_charged_partially',
        ],
    ];

    public function __construct(private readonly \Nexi_Checkout $module)
    {
    }

    public function install(): bool
    {
        foreach (self::ORDER_STATES as $state) {
            $existingStateId = $this->getOrderStateIdByTemplate($state['template']);

            if ($existingStateId !== null) {
                \Configuration::updateValue($state['configKey'], $existingStateId);
                continue;
            }

            $orderState = $this->createOrderState(
                $state['name'],
                $state['color'],
                $state['sendEmail'],
                $state['invoice'],
                $state['logable'],
                $state['shipped'],
                $state['paid'],
                $state['delivery'],
                $state['hidden'],
                $state['unremovable'],
                $state['deleted'],
                $state['template']
            );

            if (!$orderState->add()) {
                return false;
            }

            \Configuration::updateValue($state['configKey'], (int) $orderState->id);
        }

        return true;
    }

    private function createOrderState(
        string $name,
        string $color,
        bool $sendEmail,
        bool $invoice,
        bool $logable,
        bool $shipped,
        bool $paid,
        bool $delivery,
        bool $hidden,
        bool $unremovable,
        bool $deleted,
        string $template,
    ): \OrderState {
        $orderState = new \OrderState();
        $orderState->name = [];

        foreach (\Language::getLanguages(false) as $language) {
            $orderState->name[$language['id_lang']] = $name;
        }

        $orderState->color = $color;
        $orderState->send_email = $sendEmail;
        $orderState->module_name = $this->module->name;
        $orderState->invoice = $invoice;
        $orderState->logable = $logable;
        $orderState->shipped = $shipped;
        $orderState->paid = $paid;
        $orderState->delivery = $delivery;
        $orderState->hidden = $hidden;
        $orderState->unremovable = $unremovable;
        $orderState->deleted = $deleted;
        $orderState->template = $template;

        return $orderState;
    }

    private function getOrderStateIdByTemplate(string $template): ?int
    {
        $sql = new \DbQuery();
        $sql->select('os.id_order_state');
        $sql->from('order_state', 'os');
        $sql->innerJoin('order_state_lang', 'osl', 'os.id_order_state = osl.id_order_state');
        $sql->where('osl.template = "' . pSQL($template) . '"');

        $result = \Db::getInstance()->getValue($sql);

        return $result ? (int) $result : null;
    }
}
