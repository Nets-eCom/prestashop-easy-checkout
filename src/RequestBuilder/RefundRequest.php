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

namespace Nexi\Checkout\RequestBuilder;

use Nexi\Checkout\Administration\Model\ChargeItem;
use Nexi\Checkout\Helper\FormatHelper;
use NexiCheckout\Model\Request\FullRefundCharge;
use NexiCheckout\Model\Request\Item;
use NexiCheckout\Model\Request\PartialRefundCharge;
use NexiCheckout\Model\Result\RetrievePayment\Charge;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RefundRequest
{
    public function __construct(private readonly FormatHelper $helper)
    {
    }

    public function buildFullRefund(Charge $charge): FullRefundCharge
    {
        return new FullRefundCharge(
            $charge->getAmount(),
        );
    }

    /**
     * @param array{amount: float, items: array<ChargeItem>} $chargeData
     */
    public function buildPartialRefund(array $orderData, array $chargeData): PartialRefundCharge
    {
        $itemsToRefund = [];

        foreach ($chargeData['items'] as $chargeItem) {
            $orderItem = $this->findOrderItemByReference(
                $orderData,
                $chargeItem->getReference()
            );

            $quantity = (int) $chargeItem->getQuantity();
            $unitPrice = $orderItem !== [] ? $orderItem['unitPrice'] : $this->helper->priceToInt($chargeItem->getUnitPrice());

            $grossTotalAmount = $this->helper->priceToInt($chargeItem->getGrossTotalAmount());
            $netTotalAmount = $unitPrice * $quantity;
            $taxAmount = $grossTotalAmount - $netTotalAmount;

            $itemsToRefund[] = new Item(
                $orderItem !== [] ? $orderItem['name'] : $chargeItem->getName(),
                $quantity,
                'pcs',
                $unitPrice,
                $grossTotalAmount,
                $netTotalAmount,
                $orderItem !== [] ? $orderItem['reference'] : $chargeItem->getReference(),
                $orderItem !== [] ? $orderItem['taxRate'] : $chargeItem->getTaxRate() ?? null,
                $taxAmount > 0 ? $taxAmount : null
            );
        }

        return new PartialRefundCharge($itemsToRefund);
    }

    public function buildUnrelatedPartialRefund(int $refundAmount): PartialRefundCharge
    {
        $reference = \sprintf('refund %d', $refundAmount);

        return new PartialRefundCharge([new Item(
            $reference,
            1,
            'pcs',
            $refundAmount,
            $refundAmount,
            $refundAmount,
            substr($reference, 0, 128),
        )]);
    }

    /**
     * @param array<string, array<string, mixed>> $order
     *
     * @return array<string, mixed>
     */
    private function findOrderItemByReference(array $order, string $reference): array
    {
        foreach ($order['items'] as $item) {
            if ($item['reference'] === $reference) {
                return $item;
            }
        }

        return [];
    }
}
