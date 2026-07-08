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

namespace Nexi\Checkout\Order;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Event\CancelSend;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\Exception\OrderCancelException;
use Nexi\Checkout\RequestBuilder\CancelRequest;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderCancel
{
    public function __construct(
        private readonly PaymentFetcherInterface $fetcher,
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CancelRequest $requestBuilder,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly \Context $context,
    ) {
    }

    /**
     * @throws OrderCancelException
     */
    public function cancel(\Order $order): void
    {
        /** @var \OrderPayment[] $orderPayments */
        $orderPayments = $order->getOrderPaymentCollection();

        if ($orderPayments === []) {
            throw new \LogicException('No order payments found');
        }

        $paymentApi = $this->createPaymentApi();

        foreach ($orderPayments as $orderPayment) {
            $paymentId = $orderPayment->transaction_id;

            if ($paymentId === null) {
                continue;
            }

            $payment = $this->fetcher->fetchPayment($paymentId);

            if ($payment->getStatus() !== PaymentStatusEnum::RESERVED) {
                continue;
            }

            $payload = $this->requestBuilder->build($order);

            $this->logger->info('Cancel request', [
                'paymentId' => $paymentId,
                'payload' => $payload,
            ]);

            try {
                $paymentApi->cancel(
                    $paymentId,
                    $payload
                );
            } catch (PaymentApiException $e) {
                $this->logger->error('Cancel request failed', [
                    'paymentId' => $paymentId,
                    'payload' => $payload,
                ]);

                throw new OrderCancelException($paymentId, $e->getCode(), previous: $e);
            }

            $this->logger->info('Cancel request success', [
                'paymentId' => $paymentId,
                'payload' => $payload,
            ]);

            $this->dispatcher->dispatch(
                new CancelSend($order, $orderPayment)
            );
        }
    }

    private function createPaymentApi(): PaymentApi
    {
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);

        return $this->apiFactory->create(
            $this->configurationProvider->getSecretKey($shopConstraint),
            $this->configurationProvider->isLiveMode($shopConstraint)
        );
    }
}
