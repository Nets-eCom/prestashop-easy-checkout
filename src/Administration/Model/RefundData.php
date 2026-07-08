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

namespace Nexi\Checkout\Administration\Model;

use Symfony\Component\Validator\Constraints as Assert;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RefundData
{
    private \Context $context;

    /**
     * @param array<string, array{amount: float, items: array<ChargeItem>}> $charges
     */
    public function __construct(
        #[Assert\GreaterThanOrEqual(value: 0.01, message: 'nexi-checkout-payment-component.validation.errors.refund_amount')]
        private readonly float $amount,
        #[Assert\Valid]
        private readonly array $charges = [],
    ) {
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return array<string, array{amount: float, items: array<ChargeItem>}>
     */
    public function getCharges(): array
    {
        return $this->charges;
    }

    public function setContext(\Context $context): void
    {
        $this->context = $context;
    }

    public function getContext(): \Context
    {
        return $this->context;
    }
}
