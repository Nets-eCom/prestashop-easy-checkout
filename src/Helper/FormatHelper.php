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

namespace Nexi\Checkout\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class FormatHelper
{
    /**
     * regexp for filtering strings
     */
    private const ALLOWED_CHARACTERS_PATTERN = '/[^\x{00A1}-\x{00AC}\x{00AE}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}\x{0250}-\x{02AF}\x{02B0}-\x{02FF}\x{0300}-\x{036F}A-Za-z0-9\!\#\$\%\(\)*\+\,\-\.\/\:\;\\=\?\@\[\]\\^\_\`\{\}\~ ]+/u';

    public function sanitizeString(string $string): string
    {
        $string = substr($string, 0, 128);
        $name = preg_replace(self::ALLOWED_CHARACTERS_PATTERN, '', $string);

        if (empty($name)) {
            return preg_replace('/[^A-Za-z0-9() -]/', '', $string);
        }

        return $name;
    }

    public function priceToInt(float $price): int
    {
        return (int) round($price * 100);
    }

    public function priceToFloat(int $price): float
    {
        return (float) number_format($price / 100, 2, '.', '');
    }
}
