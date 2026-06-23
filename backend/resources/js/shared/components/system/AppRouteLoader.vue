<template>
  <transition name="route-loader-fade">
    <div v-if="visible" class="route-loader" role="status" aria-live="polite" aria-label="Loading route">
      <span class="route-loader__bar" />
    </div>
  </transition>
</template>

<script setup lang="ts">
interface Props {
  visible: boolean;
}

defineProps<Props>();
</script>

<style scoped>
.route-loader {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1200;
  height: 3px;
  pointer-events: none;
  background: transparent;
}

.route-loader__bar {
  display: block;
  width: 30%;
  height: 100%;
  background: linear-gradient(90deg, rgba(59, 130, 246, 0.3), rgba(96, 165, 250, 1), rgba(59, 130, 246, 0.3));
  animation: route-loader-slide 1.05s ease-in-out infinite;
}

.route-loader-fade-enter-active,
.route-loader-fade-leave-active {
  transition: opacity 0.18s ease;
}

.route-loader-fade-enter-from,
.route-loader-fade-leave-to {
  opacity: 0;
}

@keyframes route-loader-slide {
  0% {
    transform: translateX(-40%);
  }
  100% {
    transform: translateX(350%);
  }
}
</style>

