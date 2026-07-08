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

namespace Nexi\Checkout\Administration\Serializer;

use Nexi\Checkout\Administration\Model\ChargeItem;
use Nexi\Checkout\Administration\Model\RefundData;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RefundDataDenormalizer implements DenormalizerInterface
{
    /**
     * TODO_1: Resolve $format nullability conflict.
     * Symfony/PHP require explicit nullable (?string $format = null),
     * but PrestaShop Validator currently rejects it (false positive).
     * Implicit nullability ($format = null) passes PS validation,
     * but is deprecated as of PHP 8.4.
     */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        $format = null,
        array $context = [],
    ): bool {
        return $type === RefundData::class
            && \is_array($data)
            && isset($data['charges'])
            && $data['charges'] !== [];
    }

    /**
     * TODO_1: Resolve $format nullability conflict.
     */
    public function denormalize(
        mixed $data,
        string $type,
        $format = null,
        array $context = [],
    ): RefundData {
        $chargeData = [];

        foreach ($data['charges'] as $chargeId => $charge) {
            $chargeData[$chargeId] = [
                'amount' => (float) $charge['amount'],
                'items' => array_map(
                    fn (array $item): ChargeItem => new ChargeItem(
                        $chargeId,
                        $item['name'],
                        (int) $item['quantity'],
                        $item['unit'],
                        (float) $item['unitPrice'],
                        $item['amount'],
                        (float) $item['netTotalAmount'],
                        $item['reference'],
                    ),
                    $charge['items'] ?? []
                ),
            ];
        }

        return new RefundData(
            (float) $data['amount'],
            $chargeData
        );
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            RefundData::class => true,
        ];
    }
}
