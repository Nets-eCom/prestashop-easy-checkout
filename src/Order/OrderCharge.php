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

use Doctrine\ORM\EntityManagerInterface;
use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Event\ChargeSend;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\Checker\PaymentChargeabilityChecker;
use Nexi\Checkout\Order\Exception\OrderChargeException;
use Nexi\Checkout\Order\Provider\PaymentApiProvider;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Nexi\Checkout\RequestBuilder\ChargeRequest;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Model\Request\PartialCharge;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderCharge
{
    public function __construct(
        private readonly PaymentFetcherInterface $fetcher,
        private readonly ChargeRequest $requestBuilder,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly PaymentApiProvider $paymentApiProvider,
        private readonly PaymentChargeabilityChecker $chargeabilityChecker,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentDetailsRepository $paymentDetailsRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fullCharge(\Order $order): void
    {
        /** @var \OrderPayment[] $orderPayments */
        $orderPayments = $order->getOrderPaymentCollection();

        if ($orderPayments === []) {
            throw new \LogicException('No order payments found');
        }

        $paymentApi = $this->paymentApiProvider->createPaymentApi();

        foreach ($orderPayments as $orderPayment) {
            $this->fullChargePayment($order, $orderPayment, $paymentApi);
        }
    }

    public function partialCharge(\Order $order, ChargeData $chargeData): void
    {
        /** @var \OrderPayment[] $orderPayments */
        $orderPayments = $order->getOrderPaymentCollection();

        if ($orderPayments === []) {
            throw new \LogicException('No order payments found');
        }

        $paymentApi = $this->paymentApiProvider->createPaymentApi();

        foreach ($orderPayments as $orderPayment) {
            $this->partialChargePayment($order, $orderPayment, $paymentApi, $chargeData);
        }
    }

    private function fullChargePayment(\Order $order, \OrderPayment $orderPayment, PaymentApi $paymentApi): void
    {
        $paymentId = $orderPayment->transaction_id;

        if ($paymentId === null) {
            return;
        }

        $payment = $this->fetcher->fetchPayment($paymentId);

        if ($payment->getStatus() !== PaymentStatusEnum::RESERVED) {
            return;
        }

        $payload = $this->requestBuilder->buildFullCharge($payment);

        $this->logger->info('Full charge request', [
            'paymentId' => $paymentId,
            'payload' => $payload,
        ]);

        try {
            $paymentApi->charge(
                $paymentId,
                $payload
            );
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Full charge request failed', [
                'paymentId' => $paymentId,
                'payload' => $payload,
            ]);

            throw new OrderChargeException($paymentId, $paymentApiException->getCode(), previous: $paymentApiException);
        }

        $this->logger->info('Full charge request success', [
            'paymentId' => $paymentId,
            'payload' => $payload,
        ]);

        $this->dispatcher->dispatch(
            new ChargeSend($order, $orderPayment)
        );
    }

    private function partialChargePayment(\Order $order, \OrderPayment $orderPayment, PaymentApi $paymentApi, ChargeData $chargeData): void
    {
        $paymentId = $orderPayment->transaction_id;

        if ($paymentId === null) {
            return;
        }

        $payment = $this->fetcher->fetchPayment($paymentId);

        if (!$this->chargeabilityChecker->isChargeable($payment, $chargeData->getAmount())) {
            $this->logger->info('Payment in incorrect status for partial charge', [
                'paymentId' => $paymentId,
                'status' => $payment->getStatus()->value,
            ]);

            return;
        }

        $paymentDetails = $this->paymentDetailsRepository->findOneByOrderId($order->id);

        if (!$paymentDetails instanceof NexiCheckoutPaymentDetails || $paymentDetails->getPaymentId() === '') {
            throw new \LogicException(sprintf('No payment details found for order %s', $order->id));
        }

        $payload = $this->requestBuilder->buildPartialCharge($order, $chargeData, $paymentDetails);
        $this->logger->error('Partial charge request', [
            'paymentId' => $paymentId,
            'payload' => $payload,
        ]);

        try {
            $response = $paymentApi->charge($paymentId, $payload);
            $this->logger->info('Partial charge request success', [$response->getChargeId()]);
            $this->updatePaymentDetails($paymentDetails, $response->getChargeId(), $payload);
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Partial charge failed', [
                'paymentId' => $paymentId,
                'error' => $paymentApiException->getMessage(),
            ]);

            throw new OrderChargeException($paymentId, $paymentApiException->getCode(), previous: $paymentApiException);
        }

        $this->logger->info('Partial charge success', [
            'paymentId' => $paymentId,
            'chargeId' => $response->getChargeId(),
        ]);

        $this->dispatcher->dispatch(
            new ChargeSend($order, $orderPayment)
        );
    }

    private function updatePaymentDetails(
        NexiCheckoutPaymentDetails $paymentDetails,
        string $chargeId,
        PartialCharge $partialCharge,
    ): void {
        $alreadyCharged = $paymentDetails->getCharges() ?? [];

        $paymentDetails->setCharges([
            ...$alreadyCharged,
            ...[$chargeId => $partialCharge->jsonSerialize(),
            ],
        ]);

        $this->entityManager->flush();
    }
}
