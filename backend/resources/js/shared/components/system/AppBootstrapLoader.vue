<template>
  <transition name="bootstrap-fade" appear>
    <section class="bootstrap-loader" role="status" aria-live="polite" aria-label="Initializing application">
      <div class="bootstrap-loader__panel">
        <div class="bootstrap-loader__spinner" aria-hidden="true" />
        <p class="bootstrap-loader__text">Initializing application...</p>
      </div>
    </section>
  </transition>
</template>

<script setup lang="ts">
/**
 * Global bootstrap loading shell.
 *
 * WHY:
 * Startup tasks can take noticeable time (translation/auth/settings preload).
 * Showing explicit initialization feedback removes "frozen app" perception and
 * gives us a reusable loader surface for future bootstrap expansion.
 */
</script>

<style scoped>
.bootstrap-loader {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px;
  background: radial-gradient(circle at top, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 1) 65%);
}

.bootstrap-loader__panel {
  width: min(440px, 100%);
  display: grid;
  justify-items: center;
  gap: 14px;
  padding: 28px 24px;
  border-radius: 14px;
  border: 1px solid rgba(71, 85, 105, 0.5);
  background: rgba(15, 23, 42, 0.85);
  box-shadow: 0 14px 34px rgba(2, 8, 23, 0.42);
}

.bootstrap-loader__spinner {
  width: 46px;
  height: 46px;
  border-radius: 999px;
  border: 3px solid rgba(148, 163, 184, 0.25);
  border-top-color: rgba(96, 165, 250, 0.95);
  animation: bootstrap-spin 0.85s linear infinite;
}

.bootstrap-loader__text {
  margin: 0;
  color: #cbd5e1;
  font-size: 14px;
  letter-spacing: 0.01em;
}

.bootstrap-fade-enter-active,
.bootstrap-fade-leave-active {
  transition: opacity 0.22s ease;
}

.bootstrap-fade-enter-from,
.bootstrap-fade-leave-to {
  opacity: 0;
}

@keyframes bootstrap-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
