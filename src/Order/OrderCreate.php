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
use Nexi\Checkout\Configuration\OrderStateDictionary;
use Nexi\Checkout\Event\OrderCreatedEvent;
use Nexi\Checkout\Order\Exception\OrderCreateException;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentDetails;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class OrderCreate
{
    public function __construct(
        private \PaymentModule $module,
        private PaymentDetailsRepository $paymentDetailsRepository,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function createFromCart(\Cart $cart, Payment $payment): int
    {
        if ($cart->orderExists()) {
            return \Order::getIdByCartId($cart->id);
        }

        $total = (float) $payment->getOrderDetails()->getAmount() / 100;

        $this->module->validateOrder(
            (int) $cart->id,
            (int) \Configuration::get(OrderStateDictionary::PAYMENT_NEW),
            $total,
            $this->getPaymentMethodName($payment),
            null,
            ['transaction_id' => $payment->getPaymentId()],
            (int) $cart->id_currency,
            false
        );

        if (!$this->module->currentOrder) {
            throw new OrderCreateException($payment->getPaymentId());
        }

        $newOrderId = (int) $this->module->currentOrder;

        $details = $this->paymentDetailsRepository->findOneByPaymentId($payment->getPaymentId());
        $details->setOrderId($newOrderId);

        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new OrderCreatedEvent($newOrderId));

        return (int) $this->module->currentOrder;
    }

    private function getPaymentMethodName(Payment $payment): string
    {
        $paymentDetails = $payment->getPaymentDetails();

        if ($paymentDetails instanceof PaymentDetails) {
            $paymentType = $paymentDetails->getPaymentType();
            $paymentMethod = $paymentDetails->getPaymentMethod();

            return sprintf('Nexi Checkout - %s', ucfirst($paymentMethod ?? $paymentType->value));
        }

        return $this->module->displayName;
    }
}
