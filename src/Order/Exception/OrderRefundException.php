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

namespace Nexi\Checkout\Order\Exception;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderRefundException extends OrderException
{
    public function __construct(private readonly string $chargeId, ?int $code = 0, $previous = null)
    {
        if ($previous !== null && !$previous instanceof \Throwable) {
            throw new \InvalidArgumentException(sprintf('Expected instance of %s or null.', \Throwable::class));
        }

        parent::__construct(\sprintf("Couldn't refund charge with id: %s", $this->chargeId), $code, $previous);
    }

    public function getChargeId(): string
    {
        return $this->chargeId;
    }
}
