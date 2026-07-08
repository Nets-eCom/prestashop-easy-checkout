<template>
  <div class="refund-action">
    <button
        @click="openModal"
        class="btn btn-primary"
        :disabled="loading"
        v-if="shouldDisplayRefundBtn"
    >
      {{ loading ? '...' : $t('nexi-checkout-payment-component.refund.button-title') }}
    </button>

    <NexiModal v-if="modalOpen" @close="closeModal">
      <div class="modal-header">
        <h5 class="modal-title">
          {{ $t('nexi-checkout-payment-component.refund.title') }}
        </h5>
        <button type="button" class="close" @click="closeModal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <p>
          {{ $t('nexi-checkout-payment-component.refund.info') }}
          <strong>{{ details.orderId }}</strong>
        </p>

        <div class="form-group mt-3">
          <label>{{ $t('nexi-checkout-payment-component.refund.input-label') }}</label>

          <div class="input-group">
            <input
                type="number"
                class="form-control"
                :value="refundAmount"
                @input="onManualAmountInput"
                :max="details.remainingRefundAmount"
                min="0"
                step="0.01"
            />
            <span class="input-group-text">{{ details.currency }}</span>
          </div>

          <div class="form-check mt-2">
            <input
                id="refundByItems"
                type="checkbox"
                class="form-check-input"
                v-model="showItems"
                @change="onToggleItems"
            />
            <label for="refundByItems" class="form-check-label">
              {{ $t('nexi-checkout-payment-component.refund.select-items') }}
            </label>
          </div>

          <small class="text-muted">
            {{ $t('nexi-checkout-payment-component.refund.max-refund-info') }}
            <span
                @click="onClickMaxRefund"
                class="text-decoration-underline cursor-pointer"
                data-testid="maxRefundBtn"
            >
              {{ details.remainingRefundAmount }} {{ details.currency }}
            </span>
          </small>
        </div>

        <table v-if="showItems" class="table mt-3">
          <thead>
          <tr>
            <th>{{ $t('nexi-checkout-payment-component.refund.table.charge') }}</th>
            <th>{{ $t('nexi-checkout-payment-component.refund.table.item') }}</th>
            <th>{{ $t('nexi-checkout-payment-component.refund.table.qty') }}</th>
            <th>{{ $t('nexi-checkout-payment-component.refund.table.unit') }}</th>
            <th>{{ $t('nexi-checkout-payment-component.refund.table.refund-qty') }}</th>
          </tr>
          </thead>

          <tbody>
          <tr v-for="item in items" :key="item.chargeId + item.reference">
            <td>{{ item.chargeId }}</td>
            <td>{{ item.name }}</td>
            <td>{{ item.quantity }}</td>
            <td>{{ formatUnit(item) }} {{ details.currency }}</td>
            <td style="min-width: 80px; text-align: center;">
              <input
                  type="number"
                  class="form-control"
                  min="0"
                  :max="item.quantity"
                  v-model.number="item.selectedQty"
                  style="width: 100%;"
              />
            </td>
          </tr>
          </tbody>
        </table>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="closeModal">
          {{ $t('nexi-checkout-payment-component.cancel.button-action-title') }}
        </button>
        <button
            class="btn btn-primary"
            :disabled="loading || isSubmitDisabled"
            @click="refund"
        >
          {{ $t('nexi-checkout-payment-component.refund.button-title') }}
        </button>
      </div>
    </NexiModal>
  </div>
</template>

<script>
import PaymentStatus from '../constants/PaymentStatus'
import NexiModal from './NexiModal.vue'

export default {
  name: 'RefundOrder',
  components: { NexiModal },

  props: {
    details: Object,
    endpoint: String,
  },

  data() {
    return {
      modalOpen: false,
      loading: false,
      manualAmount: 0,
      items: [],
      showItems: false,
    }
  },

  computed: {
    shouldDisplayRefundBtn() {
      return [
        PaymentStatus.CHARGED,
        PaymentStatus.PARTIALLY_CHARGED,
        PaymentStatus.PARTIALLY_REFUNDED,
      ].includes(this.details.paymentStatus)
    },

    hasSelectedItems() {
      return this.items.some(item => item.selectedQty > 0)
    },

    refundAmount() {
      if (!this.hasSelectedItems) {
        return Number(this.manualAmount || 0).toFixed(2)
      }

      return this.items.reduce(
          (sum, item) => sum + item.selectedQty * this.unitGross(item),
          0
      ).toFixed(2)
    },

    isSubmitDisabled() {
      return Number(this.refundAmount) <= 0
    },
  },

  created() {
    this.items =
        this.details.charges?.map(c => ({
          chargeId: c.chargeId,
          reference: c.reference,
          name: c.name,
          unit: c.unit,
          quantity: Number(c.quantity),
          unitPrice: Number(c.unitPrice),
          grossTotalAmount: Number(c.grossTotalAmount),
          netTotalAmount: Number(c.netTotalAmount),
          selectedQty: 0,
        })) || []
  },

  methods: {
    openModal() {
      this.modalOpen = true
    },

    closeModal() {
      this.modalOpen = false
    },

    onManualAmountInput(e) {
      this.manualAmount = Number(e.target.value || 0)
    },

    onToggleItems() {
      if (!this.showItems) {
        this.items.forEach(i => (i.selectedQty = 0))
      }
    },

    onClickMaxRefund() {
      this.manualAmount = Number(this.details.remainingRefundAmount)

      this.items.forEach(item => {
        item.selectedQty = 0
      })
    },

    unitGross(item) {
      return item.grossTotalAmount / item.quantity
    },

    formatUnit(item) {
      return this.unitGross(item).toFixed(2)
    },

    async refund() {
      this.loading = true

      const charges = {}

      this.items
          .filter(i => i.selectedQty > 0)
          .forEach(i => {
            if (!charges[i.chargeId]) {
              charges[i.chargeId] = { amount: 0, items: [] }
            }

            const gross = +(this.unitGross(i) * i.selectedQty).toFixed(2)
            const net = +(i.unitPrice * i.selectedQty).toFixed(2)

            charges[i.chargeId].items.push({
              reference: i.reference,
              name: i.name,
              unit: i.unit,
              quantity: i.selectedQty,
              unitPrice: i.unitPrice,
              netTotalAmount: net,
              amount: gross,
            })

            charges[i.chargeId].amount += gross
          })

      await fetch(this.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          amount: Number(this.refundAmount),
          charges,
        }),
      })

      window.location.reload()
    },
  },
}
</script>
