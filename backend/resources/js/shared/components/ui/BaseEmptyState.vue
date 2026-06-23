<template>
  <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
    <h3 class="text-base font-semibold text-slate-800">{{ title }}</h3>
    <p class="mt-2 text-sm text-slate-600">{{ description }}</p>
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
const title = computed(() => props.title ?? t('common.generic.noDataYet'));
const description = computed(() => props.description ?? t('common.generic.noDataDescription'));
</script>
