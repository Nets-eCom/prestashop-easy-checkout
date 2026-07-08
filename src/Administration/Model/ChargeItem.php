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

readonly class ChargeItem
{
    public function __construct(
        #[Assert\NotBlank]
        private string $chargeId,
        #[Assert\NotBlank]
        private string $name,
        #[Assert\NotBlank]
        private float|int $quantity,
        #[Assert\NotBlank]
        private string $unit,
        #[Assert\NotBlank]
        private float $unitPrice,
        #[Assert\GreaterThanOrEqual(0.01)]
        private float $grossTotalAmount,
        #[Assert\GreaterThanOrEqual(0.01)]
        private float $netTotalAmount,
        #[Assert\NotBlank]
        private string $reference,
        private ?int $taxRate = null,
    ) {
    }

    public function getChargeId(): string
    {
        return $this->chargeId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getGrossTotalAmount(): float
    {
        return $this->grossTotalAmount;
    }

    public function getNetTotalAmount(): float
    {
        return $this->netTotalAmount;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getTaxRate(): ?int
    {
        return $this->taxRate;
    }
}
