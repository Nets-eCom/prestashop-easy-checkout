import { createApp } from 'vue'
import { createI18n } from 'petite-vue-i18n'
import { registerMessageResolver, resolveValue } from '@intlify/core-base'
import App from "./App.vue";

document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById("nexi-checkout-app");
  if (!el) {
    return;
  }

  const { detailsEndpoint, chargeEndpoint, refundEndpoint, cancelEndpoint } = el.dataset;

  const app = createApp(App, {
    detailsEndpoint,
    chargeEndpoint,
    refundEndpoint,
    cancelEndpoint,
  });

  registerMessageResolver(resolveValue);

  const i18n = createI18n({
    locale: 'en',
    messages: {
      en: window.nexi_checkout_translations
    }
  })

  app.use(i18n);
  app.mount('#nexi-checkout-app');
});
