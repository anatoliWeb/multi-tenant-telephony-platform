<template>
  <SettingsUpsertForm mode="create" :close-modal="closeModal" :on-saved="onSaved" />
</template>

<script setup lang="ts">
import SettingsUpsertForm from './SettingsUpsertForm.vue';
import type { SystemSettingRecord } from '../types/settings.types';

defineProps<{
  closeModal?: () => void;
  onSaved?: (setting: SystemSettingRecord) => void;
}>();
</script>

