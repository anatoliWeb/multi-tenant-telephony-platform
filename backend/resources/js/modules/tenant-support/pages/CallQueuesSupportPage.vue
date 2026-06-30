<template>
  <section class="support-page">
    <header>
      <h1>{{ t('common.tenantSupport.callQueues.title') }}</h1>
      <p>{{ t('common.tenantSupport.callQueues.subtitle') }}</p>
    </header>

    <div v-if="!activeTenantId" class="support-page__empty">
      {{ t('common.tenantSupport.selectTenantPrompt') }}
    </div>

    <div v-else-if="isLoading" class="support-page__empty">
      {{ t('common.loading') }}...
    </div>

    <div v-else-if="errorMessage" class="support-page__empty">
      {{ errorMessage }}
    </div>

    <div v-else-if="callQueues.length === 0" class="support-page__empty">
      {{ t('common.generic.noDataYet') }}
    </div>

    <div v-else>
      <p class="support-page__meta">{{ t('common.tenantSupport.fields.total') }}: {{ meta.total }}</p>
      <table class="support-table">
        <thead>
          <tr>
            <th>{{ t('common.tenantSupport.fields.name') }}</th>
            <th>{{ t('common.tenantSupport.fields.strategy') }}</th>
            <th>{{ t('common.tenantSupport.callQueues.fields.members') }}</th>
            <th>{{ t('common.tenantSupport.callQueues.fields.pausedMembers') }}</th>
            <th>{{ t('common.tenantSupport.callQueues.fields.overflow') }}</th>
            <th>{{ t('common.tenantSupport.fields.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="callQueue in callQueues" :key="callQueue.id">
            <td>
              <strong>{{ callQueue.name }}</strong>
              <div class="support-table__subtle">{{ callQueue.slug }}</div>
            </td>
            <td>{{ callQueue.strategy }}</td>
            <td>{{ callQueue.active_members_count ?? 0 }} / {{ callQueue.members_count ?? 0 }}</td>
            <td>{{ callQueue.paused_members_count ?? 0 }}</td>
            <td>{{ callQueue.overflow_destination_summary || '—' }}</td>
            <td>{{ callQueue.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useI18n } from 'vue-i18n';
import { useTenantStore } from '../../../stores/tenant.store';
import { tenantSupportService } from '../services/tenant-support.service';
import type { PaginationMeta } from '../../../types/response.types';
import type { SupportCallQueue } from '../types/tenant-support.types';

const { t } = useI18n({ useScope: 'global' });
const tenantStore = useTenantStore();
const { activeTenantId } = storeToRefs(tenantStore);
const callQueues = ref<SupportCallQueue[]>([]);
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 0, total: 0 });
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);

const load = async (): Promise<void> => {
  if (!activeTenantId.value) {
    callQueues.value = [];
    meta.value = { current_page: 1, last_page: 1, per_page: 0, total: 0 };
    isLoading.value = false;
    errorMessage.value = null;
    return;
  }

  isLoading.value = true;
  errorMessage.value = null;

  try {
    const payload = await tenantSupportService.listCallQueues();
    callQueues.value = payload.data;
    meta.value = payload.meta;
  } catch {
    callQueues.value = [];
    meta.value = { current_page: 1, last_page: 1, per_page: 0, total: 0 };
    errorMessage.value = t('common.generic.somethingWentWrong');
  } finally {
    isLoading.value = false;
  }
};

onMounted(load);
watch(activeTenantId, () => {
  void load();
});
</script>

<style scoped>
.support-page {
  display: grid;
  gap: 16px;
}

.support-page__meta,
header p,
.support-table__subtle {
  color: #94a3b8;
}

.support-page__empty {
  border: 1px dashed rgba(148, 163, 184, 0.35);
  border-radius: 14px;
  padding: 20px;
  color: #cbd5e1;
}

.support-table {
  width: 100%;
  border-collapse: collapse;
}

.support-table th,
.support-table td {
  padding: 10px 12px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.16);
  text-align: left;
  vertical-align: top;
}

.support-table__subtle {
  font-size: 12px;
  margin-top: 4px;
}
</style>
