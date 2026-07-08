<template>
  <PaymentDetails
    :detailsEndpoint="detailsEndpoint"
    :chargeEndpoint="chargeEndpoint"
    :refundEndpoint="refundEndpoint"
    :cancelEndpoint="cancelEndpoint"
  />
</template>

<script>
import { useI18n } from 'petite-vue-i18n'
import PaymentDetails from './components/PaymentDetails.vue'

export default {
  name: 'App',
  setup() {
    const { t } = useI18n()
    return { t }
  },
  components: { PaymentDetails },
  props: {
    detailsEndpoint: String,
    chargeEndpoint: String,
    refundEndpoint: String,
    cancelEndpoint: String,
  },
}
</script>
