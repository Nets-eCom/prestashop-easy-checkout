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

namespace Nexi\Checkout\Hook\PaymentOption;

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShopBundle\Translation\TranslatorComponent;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class HostedPaymentOptionBuilder
{
    private TranslatorComponent $translator;

    private \Link $link;

    public function __construct(
        private \Context $context,
    ) {
        $this->translator = $this->context->getTranslator();
        $this->link = $this->context->link;
    }

    public function build(): PaymentOption
    {
        return (new PaymentOption())
            ->setModuleName(\Nexi_Checkout::MODULE_NAME)
            ->setCallToActionText(
                $this->translator->trans(
                    'Nexi Checkout (Hosted)',
                    [],
                    'Modules.Nexicheckout.Payment'
                )
            )
            ->setAction(
                $this->link->getModuleLink(
                    \Nexi_Checkout::MODULE_NAME,
                    'hosted',
                    [],
                    true
                )
            )
            ->setLogo(
                \Media::getMediaPath(
                    _PS_MODULE_DIR_ . \Nexi_Checkout::MODULE_NAME . '/views/img/NEXI_RGB_Colore_61x20.png'
                )
            );
    }

    /**
     * @param array{name: string, label: string, enabled: bool, order: int} $method
     */
    public function buildForMethod(array $method): PaymentOption
    {
        $name = strtolower($method['name']);
        $logoPath = _PS_MODULE_DIR_ . \Nexi_Checkout::MODULE_NAME . '/views/img/payment-methods/' . $name . '.png';
        $logo = file_exists($logoPath)
            ? \Media::getMediaPath($logoPath)
            : \Media::getMediaPath(_PS_MODULE_DIR_ . \Nexi_Checkout::MODULE_NAME . '/views/img/payment-methods/default.png');

        return (new PaymentOption())
            ->setModuleName(\Nexi_Checkout::MODULE_NAME)
            ->setCallToActionText($method['label'])
            ->setAction(
                $this->link->getModuleLink(
                    \Nexi_Checkout::MODULE_NAME,
                    'hosted',
                    ['method' => $method['name']],
                    true
                )
            )
            ->setLogo($logo);
    }
}
