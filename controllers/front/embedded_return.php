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

use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}
/**
 * @property Nexi_Checkout $module
 */
class Nexi_CheckoutEmbedded_returnModuleFrontController extends ModuleFrontController
{
    private PaymentDetailsRepository $paymentDetailsRepository;
    private LoggerInterface $logger;

    public function init(): void
    {
        parent::init();

        /** @var PaymentDetailsRepository $repository */
        $repository = $this->get('doctrine.orm.entity_manager')->getRepository(NexiCheckoutPaymentDetails::class);
        $this->paymentDetailsRepository = $repository;

        /** @var LoggerInterface $logger */
        $logger = $this->get('nexi_checkout.logger');
        $this->logger = $logger;
    }

    public function postProcess(): void
    {
        $paymentId = Tools::getValue('paymentId', '');

        if ($paymentId === '') {
            $this->logger->error('EmbeddedReturn: Missing paymentId parameter');
            $this->redirectToOrderPageWithError('Payment ID is missing.');

            return;
        }

        $paymentDetails = $this->paymentDetailsRepository->findOneByPaymentId($paymentId);

        if ($paymentDetails === null) {
            $this->logger->error('EmbeddedReturn: Payment details not found', ['paymentId' => $paymentId]);
            $this->redirectToOrderPageWithError('Payment not found.');

            return;
        }

        $orderId = $paymentDetails->getOrderId();

        if ($orderId === null) {
            $this->logger->error('EmbeddedReturn: Order not yet created for payment', ['paymentId' => $paymentId]);
            $this->redirectToOrderPageWithError('Order not found.');

            return;
        }

        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            $this->logger->error('EmbeddedReturn: Invalid order', ['orderId' => $orderId, 'paymentId' => $paymentId]);
            $this->redirectToOrderPageWithError('Order not found.');

            return;
        }

        $this->logger->info('EmbeddedReturn: Redirecting to confirmation', [
            'orderId' => $orderId,
            'paymentId' => $paymentId,
        ]);

        $this->redirectToConfirmation($order);
    }

    private function redirectToConfirmation(Order $order): void
    {
        Tools::redirect(
            $this->context->link->getPageLink(
                'order-confirmation',
                true,
                null,
                [
                    'id_cart' => $order->id_cart,
                    'id_module' => (int) $this->module->id,
                    'id_order' => (int) $order->id,
                    'key' => $order->secure_key,
                ]
            )
        );
    }

    private function redirectToOrderPageWithError(string $message): void
    {
        $this->errors[] = $this->trans($message, [], 'Modules.Nexicheckout.PaymentError');
        $this->redirectWithNotifications($this->context->link->getPageLink('order', true));
    }
}
