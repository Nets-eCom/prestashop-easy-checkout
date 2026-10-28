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

use Nexi\Checkout\Mcp\Service\PaymentDetailsProvider;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpSchema;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpTool;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpToolAnnotations;
use PrestaShop\Module\PsMcpServer\Server\Exceptions\PsMcpToolCallException;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class PaymentDetailsTool
{
    private PaymentDetailsProvider $provider;

    private LoggerInterface $logger;

    public function __construct(\Nexi_Checkout $module)
    {
        $provider = $module->get(PaymentDetailsProvider::class);
        if (!$provider instanceof PaymentDetailsProvider) {
            throw new \RuntimeException('Nexi payment details service is unavailable.');
        }

        $this->provider = $provider;
        $logger = $module->get('nexi_checkout.logger');
        if (!$logger instanceof LoggerInterface) {
            throw new \RuntimeException('Nexi logger is unavailable.');
        }

        $this->logger = $logger;
    }

    #[PsMcpTool(
        name: 'nexi_payment_details',
        title: 'Nexi payment details',
        description: 'Retrieve live Nexi payment details for a numeric PrestaShop order ID in the current shop. Returns status, currency, amounts, items and charges. All monetary amounts are integers in minor currency units. Does not modify the payment or order.',
        annotations: new PsMcpToolAnnotations(
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: true,
        ),
        meta: ['category' => 'nexi_checkout'],
    )]
    #[PsMcpSchema(
        properties: [
            'orderId' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Numeric PrestaShop order ID, not an order reference or Nexi payment ID.'],
        ],
        required: ['orderId'],
    )]
    public function getPaymentDetails(int $orderId): array
    {
        try {
            return $this->provider->getDetails($orderId);
        } catch (\InvalidArgumentException $exception) {
            throw new PsMcpToolCallException($exception->getMessage(), 1);
        } catch (\Throwable $exception) {
            $this->logger->error('MCP payment details failed.', [
                'action' => 'payment_details',
                'orderId' => $orderId,
                'exception' => $exception,
            ]);

            throw new PsMcpToolCallException('Unable to retrieve Nexi payment details. Check the shop payment configuration and try again.', 1);
        }
    }
}
