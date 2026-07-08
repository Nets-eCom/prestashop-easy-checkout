<template>
  <div class="charge-action">
    <button
        @click="openModal"
        class="btn btn-primary"
        :disabled="loading"
        v-if="shouldDisplayChargeBtn"
    >
      {{ loading
        ? $t('nexi-checkout-payment-component.charge.loading')
        : $t('nexi-checkout-payment-component.charge.button-title')
      }}
    </button>

    <NexiModal v-if="modalOpen" @close="closeModal">
      <div class="modal-header">
        <h5 class="modal-title">
          {{ $t('nexi-checkout-payment-component.charge.title') }}
        </h5>
        <button type="button" class="close" @click="closeModal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <p>
          {{ $t('nexi-checkout-payment-component.charge.description') }}
          <strong>{{ orderId }}</strong>
        </p>

        <div class="form-group mt-3">
          <label>
            {{ $t('nexi-checkout-payment-component.charge.amount-label') }}
          </label>

          <div class="input-group">
            <input
                type="number"
                class="form-control"
                v-model="amount"
                :readonly="chargeByItems"
                :max="maxAmount"
                min="0"
                step="0.01"
            />
            <span class="input-group-text">{{ currency }}</span>
          </div>

          <div class="form-check mb-0 mt-2">
            <input
                id="chargeByItems"
                type="checkbox"
                class="form-check-input"
                v-model="chargeByItems"
            />
            <label for="chargeByItems" class="form-check-label">
              {{ $t('nexi-checkout-payment-component.charge.select-items') }}
            </label>
          </div>

          <small class="text-muted">
            {{ $t('nexi-checkout-payment-component.charge.max-info') }}
            <span
                class="text-decoration-underline cursor-pointer"
                @click="onClickMaxCharge"
            >
              {{ maxAmount }} {{ currency }}
            </span>
          </small>
        </div>

        <table v-if="chargeByItems" class="table mt-3">
          <thead>
          <tr>
            <th>{{ $t('nexi-checkout-payment-component.charge.table.qty') }}</th>
            <th>{{ $t('nexi-checkout-payment-component.charge.table.item') }}</th>
            <th>{{ $t('nexi-checkout-payment-component.charge.table.subtotal') }}</th>
            <th>{{ $t('nexi-checkout-payment-component.charge.table.charge-qty') }}</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="item in items" :key="item.reference">
            <td>{{ item.quantity }}x</td>
            <td>{{ item.name }}</td>
            <td>{{ item.subtotal }} {{ currency }}</td>
            <td style="max-width: 80px">
              <input
                  type="number"
                  class="form-control"
                  min="0"
                  :max="item.quantity"
                  v-model.number="item.selectedQty"
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
            @click="charge"
        >
          {{ $t('nexi-checkout-payment-component.charge.button-title') }}
        </button>
      </div>
    </NexiModal>
  </div>
</template>

<script>
import NexiModal from './NexiModal.vue'

export default {
  name: "ChargeOrder",
  components: { NexiModal },
  props: {
    orderId: Number,
    status: String,
    currency: String,
    maxAmount: String,
    endpoint: String,
    orderItems: {
      type: Array,
      required: true,
    },
  },
  data() {
    return {
      modalOpen: false,
      amount: "0.00",
      chargeByItems: false,
      items: [],
      loading: false,
    }
  },
  computed: {
    shouldDisplayChargeBtn() {
      return this.status === 'reserved' || this.status === 'partially_charged'
    },
    isSubmitDisabled() {
      if (!this.chargeByItems) {
        return parseFloat(this.amount) <= 0
      }

      return !this.items.some(item => item.selectedQty > 0)
    },
  },
  watch: {
    orderItems: {
      immediate: true,
      handler(newItems) {
        this.items = newItems.map(item => ({
          ...item,
          unitPrice: parseFloat(item.unitPrice),
          subtotal: parseFloat(item.grossTotalAmount),
          selectedQty: 0,
        }))
      }
    },
    items: {
      deep: true,
      handler() {
        if (!this.chargeByItems) {
          return
        }

        const total = this.items.reduce((sum, item) => {
          return sum + item.selectedQty * item.unitPrice
        }, 0)

        this.amount = total.toFixed(2)
      },
    },
  },
  methods: {
    openModal() {
      this.modalOpen = true
    },
    closeModal() {
      this.modalOpen = false
    },
    onClickMaxCharge() {
      if (this.chargeByItems) {
        return
      }

      this.amount = parseFloat(this.maxAmount).toFixed(2)
    },
    async charge() {
      this.loading = true

      const payload = this.chargeByItems
          ? {
            amount: parseFloat(this.amount),
            items: this.items
                .filter(item => item.selectedQty > 0)
                .map(item => ({
                  reference: item.reference,
                  quantity: item.selectedQty,
                  amount: +(item.selectedQty * item.unitPrice).toFixed(2),
                })),
          }
          : {
            amount: parseFloat(this.amount),
          }

      try {
        const res = await fetch(this.endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        })

        if (!res.ok) {
          const body = await res.json()
          console.error('Charge failed:', body.message)
        }

        window.location.reload()
      } finally {
        this.loading = false
      }
    },
  },
}
</script>
