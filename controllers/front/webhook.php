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

use Nexi\Checkout\Adapter\WebhookBuilderAdapter;
use Nexi\Checkout\Event\WebhookProcessed;
use Nexi\Checkout\Security\WebhookVoter;
use Nexi\Checkout\WebhookProcessor\WebhookProcessor;
use Nexi\Checkout\WebhookProcessor\WebhookProcessorException;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Nexi_CheckoutWebhookModuleFrontController extends ModuleFrontController
{
    private WebhookVoter $voter;
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private WebhookProcessor $webhookProcessor;
    private EventDispatcherInterface $dispatcher;

    public function init(): void
    {
        parent::init();

        /** @var WebhookVoter $voter */
        $voter = $this->get(WebhookVoter::class);
        $this->voter = $voter;

        /** @var RequestStack $requestStack */
        $requestStack = $this->get(RequestStack::class);
        $this->requestStack = $requestStack;

        /** @var LoggerInterface $logger */
        $logger = $this->get('nexi_checkout.logger');
        $this->logger = $logger;

        /** @var WebhookProcessor $webhookProcessor */
        $webhookProcessor = $this->get(WebhookProcessor::class);
        $this->webhookProcessor = $webhookProcessor;

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->get('nexi_checkout.dispatcher');
        $this->dispatcher = $dispatcher;
    }

    public function postProcess()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            $this->responseWithCode(Response::HTTP_BAD_REQUEST);
        }

        if (!$request->isMethod(Request::METHOD_POST)) {
            $this->responseWithCode(Response::HTTP_METHOD_NOT_ALLOWED);
        }

        try {
            $this->voter->denyAccessUnlessGranted(
                WebhookVoter::HEADER_MATCH,
                ShopConstraint::shop($this->context->shop->id)
            );

            $content = $request->getContent();
        } catch (AccessDeniedHttpException $e) {
            $this->responseWithCode($e->getStatusCode());
        }

        try {
            /** @phpstan-ignore-next-line */
            $webhook = WebhookBuilderAdapter::fromJson($content);
        } catch (Throwable $throwable) {
            $this->logger->critical(
                'Webhook payload parsing failed',
                [
                    'content' => $content,
                    'exception' => $throwable,
                ]
            );

            throw $throwable;
        }

        $paymentId = $webhook->getData()->getPaymentId();

        try {
            $this->webhookProcessor->process($webhook);
        } catch (WebhookProcessorException $webhookProcessorException) {
            $this->logger->error(
                'Webhook processing failed',
                [
                    'paymentId' => $paymentId,
                    'exception' => $webhookProcessorException,
                ]
            );

            $this->responseWithCode(Response::HTTP_BAD_REQUEST);
        }

        $this->dispatcher->dispatch(new WebhookProcessed($webhook, $paymentId));

        $this->responseWithCode(Response::HTTP_OK);
    }

    private function responseWithCode(int $code): never
    {
        $response = new JsonResponse([], $code);
        $response->send();

        exit;
    }
}
