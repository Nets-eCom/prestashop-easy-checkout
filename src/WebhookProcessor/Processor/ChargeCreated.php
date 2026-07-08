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

namespace Nexi\Checkout\WebhookProcessor\Processor;

use Doctrine\ORM\EntityManagerInterface;
use Nexi\Checkout\Configuration\OrderStateDictionary;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Nexi\Checkout\WebhookProcessor\Normalizer\ChargeDataNormalizer;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorException;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorInterface;
use NexiCheckout\Model\Result\RetrievePayment\Charge;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiCheckout\Model\Webhook\Data\ChargeCreatedData;
use NexiCheckout\Model\Webhook\EventNameEnum;
use NexiCheckout\Model\Webhook\WebhookInterface;
use PrestaShop\PrestaShop\Adapter\Order\Repository\OrderRepository;
use PrestaShop\PrestaShop\Core\Domain\Order\ValueObject\OrderId;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class ChargeCreated implements WebhookProcessorInterface
{
    use ProcessorLogTrait;

    public function __construct(
        private PaymentDetailsRepository $paymentDetailsRepository,
        private OrderRepository $orderRepository,
        private PaymentFetcherInterface $paymentFetcher,
        private ChargeDataNormalizer $chargeDataNormalizer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function process(WebhookInterface $webhook): void
    {
        $event = $webhook->getEvent();
        /** @var ChargeCreatedData $webhookData */
        $webhookData = $webhook->getData();
        $paymentId = $webhookData->getPaymentId();
        $chargeId = $webhookData->getChargeId();

        $this->logProcessMessage($event, 'started', $paymentId);
        $paymentDetails = $this->getPaymentDetails($paymentId);

        if ($this->isChargeAlreadyProcessed($paymentDetails, $chargeId)) {
            $this->logProcessMessage($event, sprintf('Charge data already exists for chargeId: %s', $chargeId), $paymentId);

            return;
        }

        $remotePaymentData = $this->paymentFetcher->fetchPayment($webhookData->getPaymentId());
        $this->persistChargeData($paymentDetails, $remotePaymentData);
        $this->updateOrderState($paymentDetails, $event, $remotePaymentData);
    }

    public function supports(WebhookInterface $webhook): bool
    {
        return $webhook->getEvent() === EventNameEnum::PAYMENT_CHARGE_CREATED;
    }

    /**
     * @throws WebhookProcessorException
     */
    private function getPaymentDetails(string $paymentId): NexiCheckoutPaymentDetails
    {
        $paymentDetails = $this->paymentDetailsRepository->findOneByPaymentId($paymentId);

        if (!$paymentDetails instanceof NexiCheckoutPaymentDetails) {
            throw new WebhookProcessorException(sprintf('Payment details not found for %s.', $paymentId));
        }

        return $paymentDetails;
    }

    private function isChargeAlreadyProcessed(NexiCheckoutPaymentDetails $paymentDetails, string $chargeId): bool
    {
        $charges = $paymentDetails->getCharges() ?? [];

        return isset($charges[$chargeId]);
    }

    private function persistChargeData(NexiCheckoutPaymentDetails $paymentDetails, Payment $payment): void
    {
        $paymentCharges = $payment->getCharges();
        $charges = [];
        foreach ($paymentCharges as $charge) {
            /* @var Charge $charge */
            $charges[$charge->getChargeId()] = $this->chargeDataNormalizer->normalizeChargeData($charge);
        }

        $paymentDetails->setCharges($charges);
        $this->entityManager->flush();
    }

    private function isPaymentFullyCharged(Payment $payment): bool
    {
        return $payment->getStatus() === PaymentStatusEnum::CHARGED;
    }

    private function updateOrderState(NexiCheckoutPaymentDetails $paymentDetails, EventNameEnum $event, Payment $payment): void
    {
        $order = $this->orderRepository->get(new OrderId($paymentDetails->getOrderId()));

        if (!in_array($order->getCurrentState(), $this->getTransitionableStatuses(), true)) {
            $this->logProcessMessage($event, 'order already in different state', $payment->getPaymentId());

            return;
        }

        if (!$this->isPaymentFullyCharged($payment)) {
            $order->setCurrentState((int) \Configuration::get(OrderStateDictionary::PAYMENT_CHARGED_PARTIALLY));
            $this->logProcessMessage($event, 'finished with partially charge', $payment->getPaymentId());

            return;
        }

        $order->setCurrentState((int) \Configuration::get('PS_OS_WS_PAYMENT'));
        $this->logProcessMessage($event, 'finished with full charge', $payment->getPaymentId());
    }

    private function getTransitionableStatuses(): array
    {
        return [
            (int) \Configuration::get(OrderStateDictionary::PAYMENT_ACCEPTED),
            (int) \Configuration::get(OrderStateDictionary::PAYMENT_CHARGED_PARTIALLY),
            (int) \Configuration::get(OrderStateDictionary::PAYMENT_NEW),
            (int) \Configuration::get('PS_OS_PAYMENT'),
        ];
    }
}
