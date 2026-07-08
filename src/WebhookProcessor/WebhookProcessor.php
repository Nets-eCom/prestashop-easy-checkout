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

namespace Nexi\Checkout\WebhookProcessor;

use NexiCheckout\Model\Webhook\WebhookInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class WebhookProcessor
{
    /**
     * @param WebhookProcessorInterface[] $webhookProcessors
     */
    public function __construct(
        private iterable $webhookProcessors,
    ) {
    }

    /**
     * @throws WebhookProcessorException
     * @throws \RuntimeException
     */
    public function process(WebhookInterface $webhook): void
    {
        foreach ($this->webhookProcessors as $webhookProcessor) {
            if ($webhookProcessor->supports($webhook)) {
                $webhookProcessor->process($webhook);

                return;
            }
        }

        throw new \RuntimeException('Webhook event processor missing');
    }
}
