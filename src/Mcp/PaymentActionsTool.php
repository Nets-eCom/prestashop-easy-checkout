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

namespace Nexi\Checkout\Mcp;

use Nexi\Checkout\Mcp\Service\PaymentActionService;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpSchema;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpTool;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpToolAnnotations;
use PrestaShop\Module\PsMcpServer\Server\Exceptions\PsMcpToolCallException;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class PaymentActionsTool
{
    private PaymentActionService $paymentActions;

    private LoggerInterface $logger;

    public function __construct(\Nexi_Checkout $module)
    {
        $service = $module->get(PaymentActionService::class);
        if (!$service instanceof PaymentActionService) {
            throw new \RuntimeException('Nexi payment action service is unavailable.');
        }

        $this->paymentActions = $service;
        $logger = $module->get('nexi_checkout.logger');
        if (!$logger instanceof LoggerInterface) {
            throw new \RuntimeException('Nexi logger is unavailable.');
        }

        $this->logger = $logger;
    }

    #[PsMcpTool(
        name: 'nexi_capture_payment',
        title: 'Capture Nexi payment',
        description: 'Capture a Nexi reservation for one numeric PrestaShop order ID in the current shop. Provide the required amount as a positive integer in minor currency units (1250 = EUR 12.50). Use payment details to determine the remaining reservation for a full capture. Eligibility is determined by the existing payment service; partially refunded payments may still have capturable funds. Executes immediately. On errors, retrieve fresh payment details before deciding whether to retry.',
        annotations: new PsMcpToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true),
        meta: ['category' => 'nexi_checkout'],
    )]
    #[PsMcpSchema(
        properties: [
            'orderId' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Numeric PrestaShop order ID.'],
            'amount' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Required capture amount in minor currency units.'],
        ],
        required: ['orderId', 'amount'],
    )]
    public function capture(int $orderId, int $amount): array
    {
        return $this->execute('capture', $orderId, static fn (PaymentActionService $service): array => $service->capture($orderId, $amount));
    }

    #[PsMcpTool(
        name: 'nexi_refund_payment',
        title: 'Refund Nexi payment',
        description: 'Refund a Nexi payment for one numeric PrestaShop order ID in the current shop. Provide the required amount as a positive integer in minor currency units (1250 = EUR 12.50). Use payment details to determine the remaining refundable balance for a full refund. Refunds may span multiple charges. Executes immediately. If a call fails, some charges may already have been refunded; retrieve fresh details before deciding whether to retry.',
        annotations: new PsMcpToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true),
        meta: ['category' => 'nexi_checkout'],
    )]
    #[PsMcpSchema(
        properties: [
            'orderId' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Numeric PrestaShop order ID.'],
            'amount' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Required refund amount in minor currency units.'],
        ],
        required: ['orderId', 'amount'],
    )]
    public function refund(int $orderId, int $amount): array
    {
        return $this->execute('refund', $orderId, static fn (PaymentActionService $service): array => $service->refund($orderId, $amount));
    }

    #[PsMcpTool(
        name: 'nexi_cancel_payment',
        title: 'Cancel Nexi reservation',
        description: 'Cancel an untouched Nexi reservation for one numeric PrestaShop order ID in the current shop. Captured payments cannot be cancelled; use refund instead. Executes immediately. Retrieve fresh payment details after errors before deciding whether to retry.',
        annotations: new PsMcpToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true),
        meta: ['category' => 'nexi_checkout'],
    )]
    #[PsMcpSchema(
        properties: [
            'orderId' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Numeric PrestaShop order ID.'],
        ],
        required: ['orderId'],
    )]
    public function cancel(int $orderId): array
    {
        return $this->execute('cancel', $orderId, static fn (PaymentActionService $service): array => $service->cancel($orderId));
    }

    private function execute(string $action, int $orderId, callable $callback): array
    {
        try {
            return $callback($this->paymentActions);
        } catch (\InvalidArgumentException $exception) {
            throw new PsMcpToolCallException($exception->getMessage(), 1);
        } catch (\Throwable $exception) {
            $this->logger->error('MCP payment action failed.', [
                'action' => $action,
                'orderId' => $orderId,
                'exception' => $exception,
            ]);

            throw new PsMcpToolCallException('The Nexi action could not be confirmed. It may have been partially or fully submitted. Retrieve fresh payment details before retrying.', 1);
        }
    }
}
