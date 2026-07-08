<template>
  <div class="cancel-action">
    <button
      @click="openModal"
      class="btn btn-danger"
      :disabled="isDisabled"
      v-if="shouldDisplayCancelBtn"
      :aria-busy="loading"
    >
      {{ buttonText }}
    </button>

    <NexiModal v-if="modalOpen" @close="closeModal">
      <div class="modal-header">
        <h5 class="modal-title">{{ $t('nexi-checkout-payment-component.cancel.title') }}</h5>
        <button type="button" class="close" @click="closeModal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">
          <small class="text-muted">
            {{ $t('nexi-checkout-payment-component.cancel.confirmation', {amount: orderAmount}) }}
          </small>
        </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="closeModal">
          {{ $t('nexi-checkout-payment-component.cancel.button-action-title') }}
        </button>
        <button
          class="btn btn-info"
          :disabled="loading"
          @click="handleCancel"
        >
          <span
            v-if="loading"
            class="spinner-border spinner-border-sm"
            role="status"
            aria-hidden="true"
          >
          </span>
          {{ $t('nexi-checkout-payment-component.cancel.confirm') }}
        </button>
      </div>
    </NexiModal>
  </div>
</template>

<script>
import PaymentStatus from '../constants/PaymentStatus'
import NexiModal from './NexiModal.vue'

export default {
  name: 'CancelOrder',
  components: {NexiModal},
  props: {
    status: {
      type: String,
      required: true
    },
    endpoint: {
      type: String,
      required: true
    },
    orderAmount: {
      type: String,
      required: true
    },
  },
  data() {
    return {
      modalOpen: false,
      loading: false,
      error: null
    }
  },
  computed: {
    shouldDisplayCancelBtn() {
      return PaymentStatus.isCancellable(this.status)
    },
    isDisabled() {
      return this.loading || !this.endpoint
    },
    buttonText() {
      return this.$t('nexi-checkout-payment-component.cancel.button-title');
    }
  },
  methods: {
    openModal() {
      this.modalOpen = true;
    },
    closeModal() {
      this.modalOpen = false;
    },
    handleCancel() {
      this.cancelOrder()
    },
    async cancelOrder() {
      this.loading = true
      this.error = null

      try {
        const response = await fetch(this.endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        })

        if (!response.ok) {
          const body = await response.json()
          throw new Error(body.message)
        }
      } catch (err) {
        console.error('Error canceling order:', err)
      } finally {
        this.loading = false
        window.location.reload();
      }
    }
  }
}
</script>

