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

namespace Nexi\Checkout\Fetcher;

use Nexi\Checkout\Order\Provider\PaymentApiProvider;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Model\Request\PaymentMethods;
use NexiCheckout\Model\Result\PaymentMethodsResult\PaymentMethod;
use PrestaShopBundle\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}
class PaymentMethodsFetcher implements PaymentMethodsFetcherInterface
{
    private const PAYMENT_TYPE_CARD = 'Card';

    private const TRANSLATION_DOMAIN = 'Modules.Nexicheckout.Payment';

    private readonly TranslatorInterface $translator;

    public function __construct(
        private readonly PaymentApiProvider $paymentApiProvider,
        private readonly \Context $context,
        private readonly LoggerInterface $logger,
    ) {
        $this->translator = $this->context->getTranslator();
    }

    public function getAvailablePaymentMethods($currency = null): array
    {
        if ($currency !== null && !is_string($currency)) {
            throw new \InvalidArgumentException('Expected $currency to be a string or null.');
        }

        $paymentApi = $this->paymentApiProvider->createPaymentApi();

        try {
            $paymentMethodsResult = $paymentApi->getPaymentMethods(new PaymentMethods(null, $currency, true));
        } catch (PaymentApiException $e) {
            $this->logger->error('Failed to fetch payment methods from Nexi API: ' . $e->getMessage());

            return [];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error fetching payment methods: ' . $e->getMessage());

            return [];
        }

        return $this->buildPaymentMethodOptions($paymentMethodsResult->getMethods());
    }

    /**
     * @param iterable<object> $methods
     */
    private function buildPaymentMethodOptions(iterable $methods): array
    {
        $paymentMethodOptions = [
            self::PAYMENT_TYPE_CARD => [
                'value' => self::PAYMENT_TYPE_CARD,
                'label' => $this->translator->trans(self::PAYMENT_TYPE_CARD, [], self::TRANSLATION_DOMAIN),
            ],
        ];

        foreach ($methods as $method) {
            /** @var PaymentMethod $method */
            if ($method->getPaymentType() === self::PAYMENT_TYPE_CARD) {
                continue;
            }

            $methodName = $method->getName();

            if (isset($paymentMethodOptions[$methodName])) {
                continue;
            }

            $paymentMethodOptions[$methodName] = [
                'value' => $methodName,
                'label' => $this->translator->trans($methodName, [], self::TRANSLATION_DOMAIN),
            ];
        }

        return $paymentMethodOptions;
    }
}
