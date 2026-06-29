<template>
  <section class="support-page">
    <header>
      <h1>{{ t('common.tenantSupport.phoneNumbers.title') }}</h1>
      <p>{{ t('common.tenantSupport.phoneNumbers.subtitle') }}</p>
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

    <div v-else-if="phoneNumbers.length === 0" class="support-page__empty">
      {{ t('common.generic.noDataYet') }}
    </div>

    <div v-else>
      <p class="support-page__meta">{{ t('common.tenantSupport.fields.total') }}: {{ meta.total }}</p>
      <table class="support-table">
        <thead>
          <tr>
            <th>{{ t('common.tenantSupport.fields.number') }}</th>
            <th>{{ t('common.tenantSupport.fields.assignedUser') }}</th>
            <th>{{ t('common.tenantSupport.fields.assignmentStatus') }}</th>
            <th>{{ t('common.tenantSupport.fields.primary') }}</th>
            <th>{{ t('common.tenantSupport.fields.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="phoneNumber in phoneNumbers" :key="phoneNumber.id">
            <td>{{ phoneNumber.display_number || phoneNumber.number }}</td>
            <td>{{ phoneNumber.assigned_user?.name || '—' }}</td>
            <td>{{ phoneNumber.assignment_status }}</td>
            <td>{{ phoneNumber.is_primary ? t('common.tenantSupport.values.yes') : t('common.tenantSupport.values.no') }}</td>
            <td>{{ phoneNumber.status }}</td>
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
import type { SupportPhoneNumber } from '../types/tenant-support.types';
import type { PaginationMeta } from '../../../types/response.types';

const { t } = useI18n({ useScope: 'global' });
const tenantStore = useTenantStore();
const { activeTenantId } = storeToRefs(tenantStore);
const phoneNumbers = ref<SupportPhoneNumber[]>([]);
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 0, total: 0 });
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);

const load = async (): Promise<void> => {
  if (!activeTenantId.value) {
    phoneNumbers.value = [];
    meta.value = { current_page: 1, last_page: 1, per_page: 0, total: 0 };
    isLoading.value = false;
    errorMessage.value = null;
    return;
  }

  isLoading.value = true;
  errorMessage.value = null;

  try {
    const payload = await tenantSupportService.listPhoneNumbers();
    phoneNumbers.value = payload.data;
    meta.value = payload.meta;
  } catch {
    phoneNumbers.value = [];
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
header p {
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
}
</style>
