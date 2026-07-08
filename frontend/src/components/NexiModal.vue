<template>
  <div class="modal-backdrop" @click.self="close">
    <div
        class="modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="nexi-modal-title"
    >
      <div class="modal-content">
        <slot />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "NexiModal",
  emits: ["close"],
  methods: {
    close() {
      this.$emit("close");
    },
  },
};
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0,0,0,0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
}

.modal-dialog {
  max-width: 600px;
  width: 100%;
}
</style>
