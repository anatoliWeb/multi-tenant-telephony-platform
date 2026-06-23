<template>
  <main>
    <h1>404</h1>
    <p>{{ t('common.generic.pageNotFound') }}</p>
  </main>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n({ useScope: 'global' });
</script>
