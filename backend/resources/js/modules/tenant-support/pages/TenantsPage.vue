<template>
  <section class="support-page">
    <header class="support-page__header">
      <div>
        <h1>{{ t('common.tenantSupport.tenants.title') }}</h1>
        <p>{{ t('common.tenantSupport.tenants.subtitle') }}</p>
      </div>
      <button
        v-if="activeTenantId"
        type="button"
        class="support-page__clear"
        @click="clearTenantSelection"
      >
        {{ t('common.tenantSupport.actions.clearTenant') }}
      </button>
    </header>

    <div class="support-page__grid">
      <article
        v-for="tenant in tenantOptions"
        :key="tenant.id"
        class="tenant-card"
        :data-active="tenant.id === activeTenantId || undefined"
      >
        <div>
          <h2>{{ tenant.name }}</h2>
          <p>{{ tenant.slug }}</p>
        </div>
        <small>{{ t('common.tenantSupport.fields.status') }}: {{ tenant.status }}</small>
        <button type="button" @click="selectTenant(tenant.uuid)">
          {{ tenant.id === activeTenantId ? t('common.tenantSupport.actions.selected') : t('common.tenantSupport.actions.selectTenant') }}
        </button>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useI18n } from 'vue-i18n';
import { useTenantStore } from '../../../stores/tenant.store';
import type { TenantMembershipSummary, TenantSummary } from '../../../types/tenant.types';

const { t } = useI18n({ useScope: 'global' });
const tenantStore = useTenantStore();
const { memberships, activeTenantId } = storeToRefs(tenantStore);

const tenantOptions = computed(() => memberships.value.map((item) => {
  if (isTenantMembership(item)) {
    return item.tenant;
  }

  return item;
}).filter((item): item is TenantSummary => Boolean(item)));

const selectTenant = async (tenantUuid: string): Promise<void> => {
  if (tenantUuid === activeTenantId.value) {
    return;
  }

  await tenantStore.switchTenant(tenantUuid);
};

const clearTenantSelection = (): void => {
  tenantStore.clearSelection();
};

function isTenantMembership(item: TenantMembershipSummary | TenantSummary): item is TenantMembershipSummary {
  return Object.prototype.hasOwnProperty.call(item, 'tenant');
}
</script>

<style scoped>
.support-page {
  display: grid;
  gap: 16px;
}

.support-page__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.support-page__clear,
.tenant-card button {
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: rgba(15, 23, 42, 0.6);
  color: #e2e8f0;
  border-radius: 10px;
  padding: 8px 12px;
  cursor: pointer;
}

.support-page__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.tenant-card {
  display: grid;
  gap: 10px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  border-radius: 16px;
  background: rgba(15, 23, 42, 0.58);
  padding: 16px;
}

.tenant-card[data-active='true'] {
  border-color: rgba(34, 197, 94, 0.65);
}

.tenant-card h2,
.support-page__header h1 {
  margin: 0;
}

.tenant-card p,
.support-page__header p {
  margin: 4px 0 0;
  color: #94a3b8;
}
</style>
