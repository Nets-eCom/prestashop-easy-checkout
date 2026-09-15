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

namespace Nexi\Checkout\Hook;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShopBundle\Translation\TranslatorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class OrderGridDefinitionModifier
{
    public const HOOK = 'actionOrderGridDefinitionModifier';

    public function __construct(
        private TranslatorInterface $translator,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function modify(array $params): void
    {
        $definition = $params['definition'] ?? null;
        if (!$definition instanceof GridDefinitionInterface) {
            return;
        }

        $csrfToken = $this->csrfTokenManager->getToken('nexi_checkout_bulk_charge');

        $definition->getBulkActions()->add(
            (new SubmitBulkAction('nexi_checkout_bulk_charge'))
                ->setName($this->translator->trans('Capture Nexi payments', [], 'Modules.Nexicheckout.AdminOrder'))
                ->setOptions([
                    'submit_route' => 'nexi_checkout_orders_bulk_charge',
                    'route_params' => [
                        '_csrf_token' => (string) $csrfToken,
                    ],
                    'confirm_message' => $this->translator->trans(
                        'Fully capture the selected Nexi payments?',
                        [],
                        'Modules.Nexicheckout.AdminOrder'
                    ),
                ])
        );
    }
}
