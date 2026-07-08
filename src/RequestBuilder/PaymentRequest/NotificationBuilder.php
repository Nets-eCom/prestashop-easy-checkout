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

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use NexiCheckout\Model\Request\Shared\Notification;
use NexiCheckout\Model\Request\Shared\Notification\Webhook;
use NexiCheckout\Model\Webhook\EventNameEnum;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;

if (!defined('_PS_VERSION_')) {
    exit;
}

class NotificationBuilder
{
    private const WEBHOOK_NAMES = [
        EventNameEnum::PAYMENT_CHECKOUT_COMPLETED,
        EventNameEnum::PAYMENT_RESERVATION_CREATED_V2,
        EventNameEnum::PAYMENT_CHARGE_CREATED,
        EventNameEnum::PAYMENT_REFUND_COMPLETED,
        EventNameEnum::PAYMENT_CANCEL_CREATED,
    ];

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly \Context $context,
    ) {
    }

    public function create(): Notification
    {
        return new Notification(
            $this->createWebhooks()
        );
    }

    /**
     * @return Webhook[]
     */
    private function createWebhooks(): array
    {
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);

        $webhooks = [];
        $authorizationString = $this->configurationProvider->getWebhookAuthorizationHeader($shopConstraint);
        $webhookUrl = $this->createWebhookUrl();

        foreach (self::WEBHOOK_NAMES as $eventName) {
            $webhooks[] = new Webhook(
                $eventName->value,
                $webhookUrl,
                $authorizationString
            );
        }

        return $webhooks;
    }

    private function createWebhookUrl(): string
    {
        return $this->context->link->getModuleLink(
            \Nexi_Checkout::MODULE_NAME,
            'webhook',
            [],
            true
        );
    }
}
