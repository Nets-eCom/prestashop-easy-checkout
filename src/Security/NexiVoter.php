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

namespace Nexi\Checkout\Security;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class NexiVoter
{
    /**
     * @throws AccessDeniedHttpException
     */
    public function denyAccessUnlessGranted(mixed $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if (!$this->isGranted($attribute, $subject)) {
            throw new AccessDeniedHttpException($message);
        }
    }

    protected function isGranted(string $attribute, mixed $subject): bool
    {
        if (!$this->supports($attribute, $subject)) {
            return false;
        }

        return $this->voteOnAttribute($attribute, $subject);
    }

    abstract protected function supports(string $attribute, mixed $subject): bool;

    abstract protected function voteOnAttribute(string $attribute, mixed $subject): bool;
}
