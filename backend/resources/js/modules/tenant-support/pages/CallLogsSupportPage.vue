<template>
  <section class="support-page">
    <header>
      <h1>{{ t('common.tenantSupport.callLogs.title') }}</h1>
      <p>{{ t('common.tenantSupport.callLogs.subtitle') }}</p>
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

    <div v-else class="support-page__content">
      <div class="support-stats">
        <article>
          <strong>{{ statistics.total_calls }}</strong>
          <span>{{ t('common.tenantSupport.callLogs.totalCalls') }}</span>
        </article>
        <article>
          <strong>{{ statistics.answered_calls }}</strong>
          <span>{{ t('common.tenantSupport.callLogs.answeredCalls') }}</span>
        </article>
        <article>
          <strong>{{ statistics.missed_calls }}</strong>
          <span>{{ t('common.tenantSupport.callLogs.missedCalls') }}</span>
        </article>
      </div>

      <p class="support-page__meta">{{ t('common.tenantSupport.fields.total') }}: {{ meta.total }}</p>
      <div v-if="callLogs.length === 0" class="support-page__empty">
        {{ t('common.generic.noDataYet') }}
      </div>
      <table v-else class="support-table">
        <thead>
          <tr>
            <th>{{ t('common.tenantSupport.fields.startedAt') }}</th>
            <th>{{ t('common.tenantSupport.fields.direction') }}</th>
            <th>{{ t('common.tenantSupport.fields.from') }}</th>
            <th>{{ t('common.tenantSupport.fields.to') }}</th>
            <th>{{ t('common.tenantSupport.fields.duration') }}</th>
            <th>{{ t('common.tenantSupport.fields.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="callLog in callLogs" :key="callLog.id">
            <td>{{ callLog.started_at || '—' }}</td>
            <td>{{ callLog.direction }}</td>
            <td>{{ callLog.from_number || '—' }}</td>
            <td>{{ callLog.to_number || '—' }}</td>
            <td>{{ callLog.total_seconds }}</td>
            <td>{{ callLog.status }}</td>
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
import type { SupportCallLog, SupportCallLogStatistics } from '../types/tenant-support.types';
import type { PaginationMeta } from '../../../types/response.types';

const { t } = useI18n({ useScope: 'global' });
const tenantStore = useTenantStore();
const { activeTenantId } = storeToRefs(tenantStore);
const callLogs = ref<SupportCallLog[]>([]);
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 0, total: 0 });
const statistics = ref<SupportCallLogStatistics>({
  total_calls: 0,
  answered_calls: 0,
  missed_calls: 0,
  answer_rate: 0,
});
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);

const load = async (): Promise<void> => {
  if (!activeTenantId.value) {
    callLogs.value = [];
    meta.value = { current_page: 1, last_page: 1, per_page: 0, total: 0 };
    statistics.value = { total_calls: 0, answered_calls: 0, missed_calls: 0, answer_rate: 0 };
    isLoading.value = false;
    errorMessage.value = null;
    return;
  }

  isLoading.value = true;
  errorMessage.value = null;

  try {
    const [listPayload, statisticsPayload] = await Promise.all([
      tenantSupportService.listCallLogs(),
      tenantSupportService.getCallLogStatistics(),
    ]);

    callLogs.value = listPayload.data;
    meta.value = listPayload.meta;
    statistics.value = statisticsPayload;
  } catch {
    callLogs.value = [];
    meta.value = { current_page: 1, last_page: 1, per_page: 0, total: 0 };
    statistics.value = { total_calls: 0, answered_calls: 0, missed_calls: 0, answer_rate: 0 };
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

.support-page__content {
  display: grid;
  gap: 16px;
}

.support-page__meta,
header p {
  color: #94a3b8;
}

.support-page__empty {
  border: 1px dashed rgba(148, 163, 184, 0.35);
  border-radius: 14px;
  padding: 20px;
  color: #cbd5e1;
}

.support-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.support-stats article {
  display: grid;
  gap: 4px;
  border: 1px solid rgba(148, 163, 184, 0.16);
  border-radius: 14px;
  padding: 14px;
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
}
</style>
