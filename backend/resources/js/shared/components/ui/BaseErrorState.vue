<template>
  <div class="rounded-lg border border-red-200 bg-red-50 p-6">
    <h3 class="text-base font-semibold text-red-800">{{ title }}</h3>
    <p class="mt-1 text-sm text-red-700">{{ description }}</p>
    <div v-if="$slots.default" class="mt-4">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
  title?: string;
  description?: string;
}

const props = defineProps<Props>();
const { t } = useI18n({ useScope: 'global' });
const title = computed(() => props.title ?? t('common.generic.somethingWentWrong'));
const description = computed(() => props.description ?? t('common.generic.tryAgainSoon'));
</script>
