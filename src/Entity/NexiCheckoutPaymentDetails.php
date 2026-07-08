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

namespace Nexi\Checkout\Entity;

use Doctrine\ORM\Mapping as ORM;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @ORM\Table()
 *
 * @ORM\Entity(repositoryClass="Nexi\Checkout\Repository\PaymentDetailsRepository")
 *
 * @ORM\HasLifecycleCallbacks()
 */
class NexiCheckoutPaymentDetails
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(name="order_id", type="integer", nullable=true)
     */
    private ?int $orderId = null;

    /**
     * @ORM\Column(name="order_reference", type="string", length=64, nullable=true)
     */
    private ?string $orderReference = null;

    /**
     * @ORM\Column(name="payment_id", type="string", length=64)
     */
    private string $paymentId;

    /**
     * @ORM\Column(name="embedded_cart_hash", type="string", length=64, nullable=true)
     */
    private ?string $embeddedCartHash = null;

    /**
     * @ORM\Column(name="order_data", type="json")
     */
    private array|\JsonSerializable $orderData;

    /**
     * @ORM\Column(name="charges", type="json", nullable=true)
     */
    private array|\JsonSerializable|null $charges = null;

    /**
     * @ORM\Column(name="refunded_charges", type="json", nullable=true)
     */
    private array|\JsonSerializable|null $refundedCharges = null;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     */
    private \DateTimeInterface $createdAt;

    /**
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function getOrderReference(): ?string
    {
        return $this->orderReference;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getEmbeddedCartHash(): ?string
    {
        return $this->embeddedCartHash;
    }

    public function getOrderData(): array
    {
        return $this->orderData;
    }

    public function getCharges(): ?array
    {
        return $this->charges;
    }

    public function getRefundedCharges(): ?array
    {
        return $this->refundedCharges;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @ORM\PrePersist
     */
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @ORM\PreUpdate
     */
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setOrderId(int $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function setOrderReference(string $orderReference): self
    {
        $this->orderReference = $orderReference;

        return $this;
    }

    public function setPaymentId(string $paymentId): self
    {
        $this->paymentId = $paymentId;

        return $this;
    }

    public function setEmbeddedCartHash(string $embeddedCartHash): self
    {
        $this->embeddedCartHash = $embeddedCartHash;

        return $this;
    }

    public function setOrderData(array|\JsonSerializable $orderData): self
    {
        $this->orderData = $orderData;

        return $this;
    }

    public function setCharges(array|\JsonSerializable $charges): self
    {
        $this->charges = $charges;

        return $this;
    }

    public function setRefundedCharges(array|\JsonSerializable $refundedCharges): self
    {
        $this->refundedCharges = $refundedCharges;

        return $this;
    }
}
