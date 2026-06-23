<template>
  <section class="space-y-8 p-6">
    <header class="space-y-1">
      <h1 class="text-2xl font-semibold text-slate-900">Shared UI Demo</h1>
      <p class="text-sm text-slate-600">Foundation components preview for upcoming Blade migration.</p>
    </header>

    <div class="grid gap-6 md:grid-cols-2">
      <div class="space-y-3 rounded-lg border border-slate-200 p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Buttons</h2>
        <div class="flex flex-wrap gap-2">
          <BaseButton>Primary</BaseButton>
          <BaseButton variant="secondary">Secondary</BaseButton>
          <BaseButton variant="danger">Danger</BaseButton>
          <BaseButton variant="ghost">Ghost</BaseButton>
          <BaseButton :loading="true">Loading</BaseButton>
        </div>
      </div>

      <div class="space-y-3 rounded-lg border border-slate-200 p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Inputs</h2>
        <BaseInput v-model="form.name" label="Name" placeholder="Enter name" />
        <BaseSelect v-model="form.role" label="Role" placeholder="Select role" :options="roleOptions" />
      </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <div class="space-y-3 rounded-lg border border-slate-200 p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">States</h2>
        <div class="space-y-3">
          <BaseLoader label="Loading dashboard..." />
          <BaseEmptyState />
          <BaseErrorState />
        </div>
      </div>

      <div class="space-y-3 rounded-lg border border-slate-200 p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Table</h2>
        <BaseTable :columns="tableColumns" :rows="tableRows" />
      </div>
    </div>

    <div class="rounded-lg border border-slate-200 p-4">
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Modal</h2>
      <BaseButton @click="isModalOpen = true">Open Modal</BaseButton>
      <BaseModal v-model="isModalOpen" title="Demo Modal">
        <p>This is a reusable modal container ready for future forms and details screens.</p>
        <template #footer>
          <BaseButton variant="ghost" @click="isModalOpen = false">Cancel</BaseButton>
          <BaseButton @click="isModalOpen = false">Confirm</BaseButton>
        </template>
      </BaseModal>
    </div>
  </section>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';

import BaseButton from '../../../shared/components/ui/BaseButton.vue';
import BaseInput from '../../../shared/components/ui/BaseInput.vue';
import BaseSelect from '../../../shared/components/ui/BaseSelect.vue';
import BaseModal from '../../../shared/components/ui/BaseModal.vue';
import BaseTable, { type BaseTableColumn } from '../../../shared/components/ui/BaseTable.vue';
import BaseLoader from '../../../shared/components/ui/BaseLoader.vue';
import BaseEmptyState from '../../../shared/components/ui/BaseEmptyState.vue';
import BaseErrorState from '../../../shared/components/ui/BaseErrorState.vue';

const isModalOpen = ref(false);

const form = reactive({
  name: '',
  role: '',
});

const roleOptions = [
  { value: 'admin', label: 'Admin' },
  { value: 'manager', label: 'Manager' },
  { value: 'viewer', label: 'Viewer' },
];

const tableColumns: BaseTableColumn[] = [
  { key: 'name', label: 'Name' },
  { key: 'role', label: 'Role' },
  { key: 'status', label: 'Status' },
];

const tableRows = [
  { name: 'Alice Johnson', role: 'Admin', status: 'Active' },
  { name: 'Mark Cooper', role: 'Manager', status: 'Pending' },
];
</script>

