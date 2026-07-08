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

use Nexi\Checkout\Configuration\ConfigurationProvider;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WebhookVoter extends NexiVoter
{
    public const HEADER_MATCH = 'nexicheckout_header_match';

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::HEADER_MATCH && $subject instanceof ShopConstraint;
    }

    /**
     * @param ShopConstraint $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject): bool
    {
        $context = $subject;
        $request = $this->requestStack->getMainRequest();

        if (!$this->isValidAuthHeader($request, $context)) {
            $this->logger->error(
                'Invalid Authorization Header',
                [
                    'authorization_header' => $request->headers->get('Authorization'),
                    'shopId' => $subject->getShopId(),
                ]
            );

            return false;
        }

        return true;
    }

    private function isValidAuthHeader(Request $request, ShopConstraint $constraint): bool
    {
        $expected = $this->configurationProvider->getWebhookAuthorizationHeader($constraint);
        if ($expected === '') {
            return false;
        }

        return $expected === $request->headers->get('Authorization');
    }
}
