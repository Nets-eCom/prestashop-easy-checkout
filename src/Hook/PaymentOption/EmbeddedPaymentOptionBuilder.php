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

namespace Nexi\Checkout\Hook\PaymentOption;

use Doctrine\ORM\EntityManagerInterface;
use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Entity\NexiCheckoutPaymentDetails;
use Nexi\Checkout\Helper\CartSignature;
use Nexi\Checkout\Helper\LanguageProvider;
use Nexi\Checkout\Repository\PaymentDetailsRepository;
use Nexi\Checkout\RequestBuilder\PaymentRequest;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Request\Payment\MethodConfiguration;
use NexiCheckout\Model\Request\Shared\Order;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShopBundle\Translation\TranslatorComponent;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final readonly class EmbeddedPaymentOptionBuilder
{
    private TranslatorComponent $translator;

    private \Link $link;

    private \Smarty $smarty;

    public function __construct(
        private \Context $context,
        private ConfigurationProvider $configurationProvider,
        private PaymentApiFactory $paymentApiFactory,
        private PaymentRequest $paymentRequest,
        private LanguageProvider $languageProvider,
        private EntityManagerInterface $entityManager,
        private CartSignature $cartSignature,
        private PaymentDetailsRepository $paymentDetailsRepository,
        private LoggerInterface $logger,
    ) {
        $this->translator = $this->context->getTranslator();
        $this->link = $this->context->link;
        $this->smarty = $this->context->smarty;
    }

    public function build(): PaymentOption
    {
        return $this->buildWithMethods([]);
    }

    /**
     * @param array{name: string, label: string, enabled: bool, order: int} $method
     */
    public function buildForMethod(array $method): PaymentOption
    {
        $name = strtolower($method['name']);
        $logoPath = _PS_MODULE_DIR_ . \Nexi_Checkout::MODULE_NAME . '/views/img/payment-methods/' . $name . '.png';
        $logo = file_exists($logoPath)
            ? \Media::getMediaPath($logoPath)
            : \Media::getMediaPath(_PS_MODULE_DIR_ . \Nexi_Checkout::MODULE_NAME . '/views/img/payment-methods/default.png');

        return (new PaymentOption())
            ->setModuleName(\Nexi_Checkout::MODULE_NAME)
            ->setCallToActionText($method['label'])
            ->setAdditionalInformation($this->renderSplitMethodTemplate($method))
            ->setLogo($logo);
    }

    /**
     * @param array<array{name: string, label: string, enabled: bool, order: int}> $enabledMethods
     */
    public function buildWithMethods(array $enabledMethods): PaymentOption
    {
        return (new PaymentOption())
            ->setModuleName(\Nexi_Checkout::MODULE_NAME)
            ->setAdditionalInformation($this->renderIframeTemplate($enabledMethods))
            ->setCallToActionText(
                $this->translator->trans(
                    'Nexi Checkout (Embedded)',
                    [],
                    'Modules.Nexicheckout.Payment'
                )
            )
            ->setLogo(
                \Media::getMediaPath(
                    _PS_MODULE_DIR_ . \Nexi_Checkout::MODULE_NAME . '/views/img/NEXI_RGB_Colore_61x20.png'
                )
            );
    }

    /**
     * @param array{name: string, label: string, enabled: bool, order: int} $method
     */
    private function renderSplitMethodTemplate(array $method): string
    {
        $templatePath = sprintf('%s:%s/views/templates/hook/nexi_checkout_embedded.tpl', 'module', \Nexi_Checkout::MODULE_NAME);
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);

        $this->smarty->assign([
            'isSplit' => true,
            'paymentError' => null,
            'methodName' => $method['name'],
            'checkoutKey' => $this->configurationProvider->getCheckoutKey($shopConstraint),
            'language' => $this->languageProvider->provide($this->context->language->iso_code),
            'createPaymentUrl' => $this->link->getModuleLink(
                \Nexi_Checkout::MODULE_NAME,
                'create_embedded_payment',
                [],
                true
            ),
            'validateUrl' => $this->link->getModuleLink(
                \Nexi_Checkout::MODULE_NAME,
                'embedded_create_order',
                [],
                true
            ),
            'checkoutContainerId' => 'nexi-checkout-' . strtolower($method['name']),
        ]);

        return $this->smarty->fetch($templatePath);
    }

    /**
     * @param array<array{name: string, label: string, enabled: bool, order: int}> $enabledMethods
     */
    private function renderIframeTemplate(array $enabledMethods): string
    {
        $templatePath = sprintf('%s:%s/views/templates/hook/nexi_checkout_embedded.tpl', 'module', \Nexi_Checkout::MODULE_NAME);
        $shopConstraint = ShopConstraint::shop($this->context->shop->id);
        $paymentId = $this->createEmbeddedPayment($enabledMethods);

        $this->smarty->assign([
            'isSplit' => false,
            'checkoutKey' => $this->configurationProvider->getCheckoutKey($shopConstraint),
            'language' => $this->languageProvider->provide($this->context->language->iso_code),
            'paymentId' => $paymentId,
            'paymentError' => $paymentId === null ? $this->translator->trans(
                'Unable to initialize payment. Please try again or choose another payment method.',
                [],
                'Modules.Nexicheckout.Payment'
            ) : null,
            'validateUrl' => $this->link->getModuleLink(
                \Nexi_Checkout::MODULE_NAME,
                'embedded_create_order',
                [],
                true
            ),
        ]);

        return $this->smarty->fetch($templatePath);
    }

    /**
     * @param array<array{name: string, label: string, enabled: bool, order: int}> $enabledMethods
     */
    private function createEmbeddedPayment(array $enabledMethods): ?string
    {
        $cart = $this->context->cart;

        if (!$cart || !$cart->id) {
            return null;
        }

        $shopConstraint = ShopConstraint::shop($this->context->shop->id);
        $api = $this->createPaymentApi($shopConstraint);
        $request = $this->paymentRequest->buildEmbedded($cart, $this->buildMethodConfigurations($enabledMethods));

        try {
            $embeddedPaymentResult = $api->createEmbeddedPayment($request);
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Embedded payment create error in hook', [
                'exception' => $paymentApiException,
                'request' => $request,
            ]);

            return null;
        }

        $paymentId = $embeddedPaymentResult->getPaymentId();
        $cartSignature = $this->cartSignature->generateHashSignatureByCart($cart);

        $this->storePaymentDetails($request->getOrder(), $paymentId, $cartSignature);

        return $paymentId;
    }

    /**
     * @param array<array{name: string, label: string, enabled: bool, order: int}> $enabledMethods
     *
     * @return list<MethodConfiguration>
     */
    private function buildMethodConfigurations(array $enabledMethods): array
    {
        if ($enabledMethods === []) {
            return [];
        }

        $names = [];
        foreach ($enabledMethods as $method) {
            foreach (PaymentRequest::resolveMethodNames($method['name']) as $name) {
                $names[$name] = true;
            }
        }

        return array_map(
            fn (string $name): MethodConfiguration => new MethodConfiguration($name, true),
            array_keys($names)
        );
    }

    private function createPaymentApi(ShopConstraint $shopConstraint): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($shopConstraint),
            $this->configurationProvider->isLiveMode($shopConstraint),
        );
    }

    private function storePaymentDetails(Order $order, string $paymentId, string $cartSignature): void
    {
        $cartReference = $order->getReference();
        $details = $this->paymentDetailsRepository->findOneByOrderReference($cartReference);

        if (!$details instanceof NexiCheckoutPaymentDetails) {
            $details = new NexiCheckoutPaymentDetails();
            $details->setOrderReference($cartReference);
        }

        $details->setPaymentId($paymentId);
        $details->setEmbeddedCartHash($cartSignature);
        $details->setOrderData($order);

        $this->entityManager->persist($details);
        $this->entityManager->flush();
    }
}
