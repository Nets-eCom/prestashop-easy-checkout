<template>
  <div class="card nexi-payment-card">
    <div class="card-header">
      <h3 class="card-header-title">
        {{ $t('nexi-checkout-payment-component.payment-details.title') }}
      </h3>
    </div>

    <div class="card-body payment-details">
      <div v-if="loading" class="text-center py-4">
        <div class="spinner-border" role="status">
          <span class="sr-only">...</span>
        </div>
      </div>

      <div v-else-if="error" class="alert alert-danger" role="alert">
        {{ $t('nexi-checkout-payment-component.payment-details.fetch-error') }}Failed to load payment details. Please try again.
      </div>

      <template v-else>
        <div class="row mb-3 align-items-center">
          <div class="col d-flex align-items-center">
            <label class="form-control-label mb-0 mr-2">{{ $t('nexi-checkout-payment-component.payment-details.status-label') }}</label>
            <span :class="['badge', statusClass, 'px-3', 'py-2']">
              {{ $t(statusTcString) }}
            </span>
          </div>

          <div class="col-auto">
            <a href="#" class="btn btn-link" @click.prevent="showHistory">
              {{ $t('nexi-checkout-payment-component.payment-details.history-link') }}
            </a>
          </div>
        </div>

        <hr />

      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label class="form-control-label">{{ $t('nexi-checkout-payment-component.payment-details.payment-via') }}</label>
            <input class="form-control" :value="details.paymentVia" disabled />
          </div>

          <div class="form-group">
            <label class="form-control-label">{{ $t('nexi-checkout-payment-component.payment-details.order-amount') }}</label>
            <input class="form-control" :value="formattedOrderAmount" disabled />
          </div>

          <div class="form-group">
            <label class="form-control-label">{{ $t('nexi-checkout-payment-component.payment-details.charged-amount') }}</label>
            <input class="form-control" :value="formattedChargedAmount" disabled />
          </div>

          <div class="form-group" v-if="shouldDisplayRefundField">
            <label class="form-control-label">{{ $t('nexi-checkout-payment-component.payment-details.refunded-amount') }}</label>
            <input class="form-control" :value="formattedRefundedAmount" disabled />
          </div>
        </div>

        <div class="col-md-6">
          <div class="form-group">
            <label class="form-control-label">{{ $t('nexi-checkout-payment-component.payment-details.payment-method') }}</label>
            <input class="form-control" :value="details.paymentMethod" disabled />
          </div>

          <div class="form-group">
            <label class="form-control-label">{{ $t('nexi-checkout-payment-component.payment-details.payment-id') }}</label>
            <input class="form-control" :value="details.paymentId" disabled />
          </div>

          <div class="form-group">
            <label class="form-control-label">{{ $t('nexi-checkout-payment-component.payment-details.time-order') }}</label>
            <input class="form-control" :value="details.orderTime" disabled />
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-12 mt-4 d-flex gap-3 action-buttons">
          <CancelOrder
            :status="details.paymentStatus"
            :endpoint="cancelEndpoint"
            :orderAmount="formattedOrderAmount"
          />

          <ChargeOrder
              v-if="remainingItemsToCharge.length > 0"
              :orderItems="remainingItemsToCharge"
              :orderId="details.orderId"
              :status="details.paymentStatus"
              :currency="details.currency"
              :maxAmount="details.remainingChargeAmount"
              :endpoint="chargeEndpoint"
          />

          <RefundOrder
            :details="details"
            :endpoint="refundEndpoint"
          />
        </div>
      </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  width: 100%;
}
</style>

<script>
import CancelOrder from './CancelOrder.vue';
import ChargeOrder from "./ChargeOrder.vue";
import RefundOrder from "./RefundOrder.vue";
import PaymentStatus from '../constants/PaymentStatus'

export default {
  name: "PaymentDetails",
  components: { CancelOrder, ChargeOrder, RefundOrder },
  props: {
    chargeEndpoint: {
      type: String,
      required: true,
    },
    refundEndpoint: {
      type: String,
      required: true
    },
    detailsEndpoint: {
      type: String,
      required: true
    },
    cancelEndpoint: {
      type: String,
      required: true
    },
  },
  data() {
    return {
      loading: true,
      error: false,
      details: {
        orderId: null,
        paymentStatus: '',
        paymentVia: '',
        orderAmount: "0.00",
        chargedAmount: 0,
        charges: [],
        remainingChargeAmount: 0,
        refundedAmount: 0,
        remainingRefundAmount: 0,
        currency: '',
        paymentMethod: '',
        paymentId: '',
        orderTime: '',
      },
    }
  },
  computed: {
    remainingItemsToCharge() {
      if (!this.details.items) {
        return []
      }

      return this.details.items
          .map(item => {
            const remainingQty = item.quantity - (item.qtyCharged || 0)

            if (remainingQty <= 0) {
              return null
            }

            return {
              ...item,
              quantity: remainingQty
            }
          })
          .filter(Boolean)
    },
    statusClass() {
      return PaymentStatus.getBadgeClass(this.details.paymentStatus)
    },
    statusTcString() {
      return PaymentStatus.getTcString(this.details.paymentStatus)
    },
    formattedOrderAmount() {
      return this.formatAmount(this.details.orderAmount)
    },
    formattedChargedAmount() {
      return this.formatAmount(this.details.chargedAmount)
    },
    formattedRefundedAmount() {
      return this.formatAmount(this.details.refundedAmount)
    },
    shouldDisplayRefundField() {
      return parseFloat(this.details.refundedAmount) > 0
    }
  },
  async created() {
    await this.fetchPaymentDetails()
  },
  methods: {
    async fetchPaymentDetails() {
      try {
        this.loading = true
        this.error = false

        const response = await fetch(this.detailsEndpoint)

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }

        this.details = await response.json()
      } catch (error) {
        console.error('Error loading payment details:', error)
        this.error = true
      } finally {
        this.loading = false
      }
    },
    formatAmount(amount) {
      return `${amount} ${this.details.currency}`
    },
    showHistory() {
      // TODO: Implement payment history modal/navigation
      console.log('Show payment history')
    }
  }
}
</script>
