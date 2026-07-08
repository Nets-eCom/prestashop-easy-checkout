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

namespace Nexi\Checkout\WebhookProcessor\Normalizer;

use NexiCheckout\Model\Result\RetrievePayment\Item;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ChargeDataNormalizer
{
    public function normalizeChargeData($data): array
    {
        return [
            'amount' => $data->getAmount(),
            'chargeId' => $data->getChargeId(),
            'surchargeAmount' => $data->getSurchargeAmount(),
            'createdAt' => $data->getCreated()->format('Y-m-d\TH:i:s.u\Z'),
            'orderItems' => $this->mapOrderItems($data->getOrderItems()),
        ];
    }

    private function mapOrderItems(array $orderItems): array
    {
        return array_map(static fn (Item $item): array => [
            'name' => $item->getName(),
            'quantity' => $item->getQuantity(),
            'unit' => $item->getUnit(),
            'unitPrice' => $item->getUnitPrice(),
            'grossTotalAmount' => $item->getGrossTotalAmount(),
            'netTotalAmount' => $item->getNetTotalAmount(),
            'reference' => $item->getReference(),
            'taxRate' => $item->getTaxRate(),
            'taxAmount' => $item->getTaxAmount(),
        ], $orderItems);
    }
}
