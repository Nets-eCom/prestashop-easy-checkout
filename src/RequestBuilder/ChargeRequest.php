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

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\RequestBuilder\PaymentRequest\ItemsBuilder;
use NexiCheckout\Model\Request\FullCharge;
use NexiCheckout\Model\Request\PartialCharge;
use NexiCheckout\Model\Result\RetrievePayment\Payment;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ChargeRequest
{
    public function __construct(private readonly ItemsBuilder $itemsBuilder)
    {
    }

    public function buildFullCharge(Payment $payment): FullCharge
    {
        return new FullCharge($payment->getOrderDetails()->getAmount());
    }

    public function buildPartialCharge(\Order $order, ChargeData $chargeData, NexiCheckoutPaymentDetails $paymentDetails): PartialCharge
    {
        if ($chargeData->getItems() === []) {
            return new PartialCharge(
                [$this->itemsBuilder->createUnrelatedPartialChargeItem($order, $chargeData->getAmount(), $paymentDetails->getCharges())],
                false
            );
        }

        return new PartialCharge(
            $this->itemsBuilder->createForCharge($chargeData, $order->getCartProducts()),
        );
    }
}
