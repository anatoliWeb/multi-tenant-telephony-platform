<template>
  <section class="base-form-section c-card">
    <header v-if="title || description" class="base-form-section__header">
      <h3 v-if="title" class="base-form-section__title">{{ title }}</h3>
      <p v-if="description" class="base-form-section__description">{{ description }}</p>
    </header>

    <div class="base-form-section__body" :class="[`is-${layout}`]">
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
import type { FormLayout } from '../types/form.types';

interface Props {
  title?: string;
  description?: string;
  layout?: FormLayout;
}

withDefaults(defineProps<Props>(), {
  title: '',
  description: '',
  layout: 'vertical',
});
</script>

<style scoped>
.base-form-section{margin-top:0;display:grid;gap:12px;min-width:0}
.base-form-section__title{margin:0;color:#f8fafc;font-size:15px}
.base-form-section__description{margin:5px 0 0;color:#94a3b8;font-size:12px}
.base-form-section__body{display:grid;gap:12px;min-width:0}
.base-form-section__body.is-grid{grid-template-columns:repeat(2,minmax(0,1fr));column-gap:12px;row-gap:12px}
@media (max-width:960px){.base-form-section__body.is-grid{grid-template-columns:1fr}}
</style>
