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

use Nexi\Checkout\Configuration\OrderStateDictionary;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorException;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorInterface;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiCheckout\Model\Webhook\EventNameEnum;
use NexiCheckout\Model\Webhook\WebhookInterface;
use PrestaShop\PrestaShop\Adapter\Order\Repository\OrderRepository;
use PrestaShop\PrestaShop\Core\Domain\Order\ValueObject\OrderId;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class RefundCompleted implements WebhookProcessorInterface
{
    use ProcessorLogTrait;

    public function __construct(
        private PaymentDetailsRepository $paymentDetailsRepository,
        private OrderRepository $orderRepository,
        private PaymentFetcherInterface $paymentFetcher,
        private LoggerInterface $logger,
    ) {
    }

    public function process(WebhookInterface $webhook): void
    {
        $paymentId = $webhook->getData()->getPaymentId();

        $event = $webhook->getEvent();
        $this->logProcessMessage($event, 'started', $paymentId);

        $details = $this->paymentDetailsRepository->findOneByPaymentId($paymentId);

        if (!$details instanceof NexiCheckoutPaymentDetails) {
            throw new WebhookProcessorException(sprintf("Payment details with id: %s wasn't found.", $paymentId));
        }

        $order = $this->orderRepository->get(new OrderId($details->getOrderId()));

        if (!$this->isPaymentFullyRefunded($paymentId)) {
            $order->setCurrentState((int) \Configuration::get(OrderStateDictionary::PAYMENT_REFUNDED_PARTIALLY));

            $this->logProcessMessage($event, 'finished', $paymentId);

            return;
        }

        $order->setCurrentState((int) \Configuration::get('PS_OS_REFUND'));
        $this->logProcessMessage($event, 'finished', $paymentId);
    }

    public function supports(WebhookInterface $webhook): bool
    {
        return $webhook->getEvent() === EventNameEnum::PAYMENT_REFUND_COMPLETED;
    }

    private function isPaymentFullyRefunded(string $paymentId): bool
    {
        $payment = $this->paymentFetcher->fetchPayment($paymentId);

        return $payment->getStatus() === PaymentStatusEnum::REFUNDED;
    }
}
