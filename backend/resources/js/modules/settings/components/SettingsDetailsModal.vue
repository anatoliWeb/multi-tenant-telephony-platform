<template>
  <section class="settings-details">
    <dl class="settings-details__grid">
      <div class="settings-details__row">
        <dt>Key</dt>
        <dd>{{ setting.key }}</dd>
      </div>
      <div class="settings-details__row">
        <dt>Label</dt>
        <dd>{{ setting.label }}</dd>
      </div>
      <div class="settings-details__row">
        <dt>Description</dt>
        <dd>{{ setting.description || '-' }}</dd>
      </div>
      <div class="settings-details__row">
        <dt>Group</dt>
        <dd>{{ setting.group }}</dd>
      </div>
      <div class="settings-details__row">
        <dt>Type</dt>
        <dd>{{ setting.type }}</dd>
      </div>
      <div class="settings-details__row">
        <dt>Value</dt>
        <dd>{{ stringify(setting.value) }}</dd>
      </div>
      <div class="settings-details__row">
        <dt>Default</dt>
        <dd>{{ stringify(setting.default_value) }}</dd>
      </div>
      <div class="settings-details__row">
        <dt>Scope</dt>
        <dd>{{ setting.scope.type }}</dd>
      </div>
      <div class="settings-details__row">
        <dt>Flags</dt>
        <dd>
          <span class="settings-details__badge" :class="{ 'is-on': setting.is_frontend }">FE</span>
          <span class="settings-details__badge" :class="{ 'is-on': setting.is_backend }">BE</span>
          <span class="settings-details__badge" :class="{ 'is-on': setting.is_public }">PUB</span>
          <span class="settings-details__badge" :class="{ 'is-on': setting.is_encrypted }">ENC</span>
          <span class="settings-details__badge" :class="{ 'is-on': setting.is_active }">ACT</span>
          <span class="settings-details__badge" :class="{ 'is-on': setting.is_system }">SYS</span>
        </dd>
      </div>
    </dl>
  </section>
</template>

<script setup lang="ts">
import type { SystemSettingRecord } from '../types/settings.types';

defineProps<{
  setting: SystemSettingRecord;
}>();

const stringify = (value: unknown): string => {
  if (value === null || value === undefined) return '-';
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  return JSON.stringify(value);
};
</script>

<style scoped>
.settings-details{display:grid;gap:10px}
.settings-details__grid{display:grid;gap:8px;margin:0}
.settings-details__row{display:grid;grid-template-columns:160px 1fr;gap:10px}
.settings-details__row dt{color:#94a3b8;font-size:12px}
.settings-details__row dd{margin:0;color:#e2e8f0;font-size:13px;word-break:break-word}
.settings-details__badge{display:inline-block;border-radius:999px;padding:2px 7px;border:1px solid rgba(71,85,105,.6);font-size:11px;color:#94a3b8;margin-right:4px}
.settings-details__badge.is-on{color:#6ee7b7;border-color:rgba(16,185,129,.5);background:rgba(16,185,129,.15)}
@media (max-width:760px){.settings-details__row{grid-template-columns:1fr}}
</style>

