<template>
  <transition name="global-loader-fade">
    <section
      v-if="visible"
      class="global-loader"
      role="status"
      aria-live="polite"
      :aria-label="message"
    >
      <div class="global-loader__backdrop" />
      <article class="global-loader__card">
        <div class="global-loader__orb" aria-hidden="true" />
        <p class="global-loader__text">{{ message }}</p>
      </article>
    </section>
  </transition>
</template>

<script setup lang="ts">
interface Props {
  visible: boolean;
  message: string;
}

defineProps<Props>();
</script>

<style scoped>
.global-loader {
  position: fixed;
  inset: 0;
  z-index: 5000;
  display: grid;
  place-items: center;
  padding: 20px;
}

.global-loader__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(2, 6, 23, 0.58);
  backdrop-filter: blur(7px);
}

.global-loader__card {
  position: relative;
  width: min(420px, 100%);
  display: grid;
  justify-items: center;
  gap: 14px;
  padding: 24px 22px;
  border-radius: 14px;
  border: 1px solid rgba(71, 85, 105, 0.56);
  background: rgba(15, 23, 42, 0.9);
  box-shadow: 0 20px 48px rgba(2, 8, 23, 0.5);
}

.global-loader__orb {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  border: 3px solid rgba(148, 163, 184, 0.26);
  border-top-color: rgba(96, 165, 250, 1);
  animation: global-loader-spin 0.85s linear infinite;
}

.global-loader__text {
  margin: 0;
  color: #dbe6f7;
  font-size: 14px;
  letter-spacing: 0.01em;
  text-align: center;
}

.global-loader-fade-enter-active,
.global-loader-fade-leave-active {
  transition: opacity 0.18s ease;
}

.global-loader-fade-enter-from,
.global-loader-fade-leave-to {
  opacity: 0;
}

@keyframes global-loader-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>

