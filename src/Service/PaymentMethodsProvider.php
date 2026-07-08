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

namespace Nexi\Checkout\Service;

use Nexi\Checkout\Fetcher\PaymentMethodsFetcherInterface;
use Nexi\Checkout\Service\Exception\PaymentMethodsNotAvailableException;
use Nexi\Checkout\Service\Exception\PaymentMethodsProviderException;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentMethodsProvider
{
    public function __construct(
        private readonly PaymentMethodsFetcherInterface $paymentMethodsFetcher,
        private readonly PaymentMethodsConfigurationService $paymentMethodsConfigurationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<array{name: string, label: string, enabled: bool, order: int}>
     *
     * @throws PaymentMethodsNotAvailableException
     * @throws PaymentMethodsProviderException
     */
    public function provide(?string $currency): array
    {
        try {
            $availableMethods = $this->paymentMethodsFetcher->getAvailablePaymentMethods($currency);

            if ($availableMethods === []) {
                throw new PaymentMethodsNotAvailableException();
            }

            $savedConfiguration = $this->paymentMethodsConfigurationService->getAllPaymentMethods();

            return $this->mergeMethodsWithConfiguration($availableMethods, $savedConfiguration);
        } catch (PaymentMethodsProviderException $exception) {
            $this->logger->error('Failed to fetch payment methods: ' . $exception->getMessage());

            throw $exception;
        } catch (\Exception $exception) {
            $this->logger->error('Failed to fetch payment methods: ' . $exception->getMessage());

            throw new PaymentMethodsProviderException('Failed to fetch payment methods: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @param array<array{value: string, label: string}> $availableMethods
     * @param array<array{name: string, enabled?: bool, order?: int}> $savedConfiguration
     *
     * @return array<array{name: string, label: string, enabled: bool, order: int}>
     */
    private function mergeMethodsWithConfiguration(array $availableMethods, array $savedConfiguration): array
    {
        $methods = [];
        $savedMethodsMap = [];

        foreach ($savedConfiguration as $index => $savedMethod) {
            $savedMethodsMap[$savedMethod['name']] = [
                'enabled' => $savedMethod['enabled'] ?? false,
                'order' => $savedMethod['order'] ?? $index,
            ];
        }

        foreach ($availableMethods as $index => $apiMethod) {
            $methodName = $apiMethod['value'];
            $saved = $savedMethodsMap[$methodName] ?? null;

            $methods[] = [
                'name' => $methodName,
                'label' => $apiMethod['label'],
                'enabled' => $saved['enabled'] ?? false,
                'order' => $saved['order'] ?? $index,
            ];
        }

        usort($methods, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $methods;
    }
}
