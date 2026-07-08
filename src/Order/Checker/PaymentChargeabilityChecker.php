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

namespace Nexi\Checkout\Order\Checker;

use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentChargeabilityChecker
{
    public function isChargeable(Payment $payment, float $partialAmount): bool
    {
        $status = $payment->getStatus();

        if (\in_array(
            $status,
            [
                PaymentStatusEnum::RESERVED,
                PaymentStatusEnum::PARTIALLY_CHARGED,
            ],
            true
        )) {
            return true;
        }

        return $status === PaymentStatusEnum::PARTIALLY_REFUNDED
            && $payment->getSummary()->getChargedAmount() > $partialAmount;
    }
}
