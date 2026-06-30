<template>
  <section class="support-page">
    <header>
      <h1>{{ t('common.tenantSupport.ivr.title') }}</h1>
      <p>{{ t('common.tenantSupport.ivr.subtitle') }}</p>
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

    <div v-else-if="ivrMenus.length === 0" class="support-page__empty">
      {{ t('common.generic.noDataYet') }}
    </div>

    <div v-else>
      <p class="support-page__meta">{{ t('common.tenantSupport.fields.total') }}: {{ meta.total }}</p>
      <table class="support-table">
        <thead>
          <tr>
            <th>{{ t('common.tenantSupport.fields.name') }}</th>
            <th>{{ t('common.tenantSupport.fields.status') }}</th>
            <th>{{ t('common.tenantSupport.ivr.fields.options') }}</th>
            <th>{{ t('common.tenantSupport.ivr.fields.timeoutRouting') }}</th>
            <th>{{ t('common.tenantSupport.ivr.fields.invalidRouting') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ivrMenu in ivrMenus" :key="ivrMenu.id">
            <td>
              <strong>{{ ivrMenu.name }}</strong>
              <div class="support-table__subtle">{{ ivrMenu.slug }}</div>
            </td>
            <td>{{ ivrMenu.status }}</td>
            <td>{{ ivrMenu.active_options_count ?? 0 }} / {{ ivrMenu.options_count ?? 0 }}</td>
            <td>{{ ivrMenu.timeout_destination_summary || '-' }}</td>
            <td>{{ ivrMenu.invalid_destination_summary || '-' }}</td>
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
import type { SupportIvrMenu } from '../types/tenant-support.types';

const { t } = useI18n({ useScope: 'global' });
const tenantStore = useTenantStore();
const { activeTenantId } = storeToRefs(tenantStore);
const ivrMenus = ref<SupportIvrMenu[]>([]);
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 0, total: 0 });
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);

const load = async (): Promise<void> => {
  if (!activeTenantId.value) {
    ivrMenus.value = [];
    meta.value = { current_page: 1, last_page: 1, per_page: 0, total: 0 };
    isLoading.value = false;
    errorMessage.value = null;
    return;
  }

  isLoading.value = true;
  errorMessage.value = null;

  try {
    const payload = await tenantSupportService.listIvrMenus();
    ivrMenus.value = payload.data;
    meta.value = payload.meta;
  } catch {
    ivrMenus.value = [];
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
