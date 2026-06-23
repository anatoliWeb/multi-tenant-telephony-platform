<template>
  <section class="dashboard-placeholder">
    <h2 class="dashboard-placeholder__title">{{ title }}</h2>
    <p class="dashboard-placeholder__text">
      This module is prepared in the Vue shell and will be migrated from Blade incrementally.
    </p>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const title = computed(() => (route.meta.title as string | undefined) ?? 'Module');
</script>

