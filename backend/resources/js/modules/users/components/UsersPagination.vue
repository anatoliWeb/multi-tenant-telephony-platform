<template>
  <div class="table-pagination" v-if="totalItems > 0">
    <div class="table-pagination__left">
      <div class="table-pagination__per-page">
        <BaseDropdown>
          <template #trigger="{ isOpen }">
            <button type="button" class="table-pagination__size-trigger" :class="{ 'is-open': isOpen }">
              <span>{{ perPage }}</span>
              <span class="table-pagination__size-caret">{{ isOpen ? '^' : 'v' }}</span>
            </button>
          </template>

          <template #default="{ close }">
            <button
              v-for="size in pageSizes"
              :key="size"
              type="button"
              class="table-pagination__size-option"
              :class="{ 'is-active': size === perPage }"
              @click="onPerPageSelect(size, close)"
            >
              {{ size }}
            </button>
          </template>
        </BaseDropdown>

        <span>{{ t('common.labels.perPage') }}</span>
      </div>

      <span class="table-pagination__range">{{ rangeStart }}-{{ rangeEnd }} of {{ totalItems }}</span>
    </div>

    <div class="table-pagination__right">
      <button type="button" class="table-pagination__icon-btn" :disabled="isFirstPage" :aria-label="t('common.labels.firstPage')" @click="$emit('change', 1)">
        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M14.5 4.5a1 1 0 0 1 0 1.4L10.4 10l4.1 4.1a1 1 0 1 1-1.4 1.4l-4.8-4.8a1 1 0 0 1 0-1.4l4.8-4.8a1 1 0 0 1 1.4 0zM7.5 4.5a1 1 0 0 1 0 1.4L3.4 10l4.1 4.1a1 1 0 0 1-1.4 1.4l-4.8-4.8a1 1 0 0 1 0-1.4l4.8-4.8a1 1 0 0 1 1.4 0z"/></svg>
      </button>

      <button type="button" class="table-pagination__icon-btn" :disabled="isFirstPage" :aria-label="t('common.labels.previousPage')" @click="$emit('change', currentPage - 1)">
        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12.9 4.5a1 1 0 0 1 0 1.4L8.8 10l4.1 4.1a1 1 0 1 1-1.4 1.4l-4.8-4.8a1 1 0 0 1 0-1.4l4.8-4.8a1 1 0 0 1 1.4 0z"/></svg>
      </button>

      <span class="table-pagination__page-indicator">{{ currentPage }} / {{ totalPages }}</span>

      <button type="button" class="table-pagination__icon-btn" :disabled="isLastPage" :aria-label="t('common.labels.nextPage')" @click="$emit('change', currentPage + 1)">
        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M7.1 15.5a1 1 0 0 1 0-1.4l4.1-4.1-4.1-4.1a1 1 0 0 1 1.4-1.4l4.8 4.8a1 1 0 0 1 0 1.4l-4.8 4.8a1 1 0 0 1-1.4 0z"/></svg>
      </button>

      <button type="button" class="table-pagination__icon-btn" :disabled="isLastPage" :aria-label="t('common.labels.lastPage')" @click="$emit('change', totalPages)">
        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5.5 15.5a1 1 0 0 1 0-1.4L9.6 10 5.5 5.9a1 1 0 1 1 1.4-1.4l4.8 4.8a1 1 0 0 1 0 1.4l-4.8 4.8a1 1 0 0 1-1.4 0zm7-11a1 1 0 0 1 0 1.4L8.4 10l4.1 4.1a1 1 0 1 1-1.4 1.4l-4.8-4.8a1 1 0 0 1 0-1.4l4.8-4.8a1 1 0 0 1 1.4 0z"/></svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import BaseDropdown from '../../../shared/components/ui/BaseDropdown.vue';

/**
 * Reusable table pagination primitive.
 *
 * WHY CENTRALIZED DROPDOWN:
 * Native selects render inconsistently across browsers and break visual
 * continuity in SaaS shells. Reusing BaseDropdown keeps page-size selection
 * aligned with language/profile menus and future filter/sort popovers.
 */
interface Props {
  currentPage: number;
  totalPages: number;
  perPage: number;
  totalItems: number;
  rangeStart: number;
  rangeEnd: number;
  pageSizes?: number[];
}

const props = withDefaults(defineProps<Props>(), {
  pageSizes: () => [10, 25, 50, 100],
});

const emit = defineEmits<{
  change: [page: number];
  'update:per-page': [size: number];
}>();
const { t } = useI18n({ useScope: 'global' });

const isFirstPage = computed(() => props.currentPage <= 1);
const isLastPage = computed(() => props.currentPage >= props.totalPages);

const onPerPageSelect = (size: number, close: () => void): void => {
  emit('update:per-page', size);
  close();
};
</script>

<style scoped>
.table-pagination {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.table-pagination__left,
.table-pagination__right {
  display: inline-flex;
  align-items: center;
  gap: 9px;
}

.table-pagination__per-page {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: #94a3b8;
  font-size: 12px;
}

.table-pagination__size-trigger {
  height: 32px;
  border-radius: 8px;
  border: 1px solid rgba(71, 85, 105, 0.6);
  background: rgba(15, 23, 42, 0.7);
  color: #e2e8f0;
  min-width: 58px;
  padding: 0 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
}

.table-pagination__size-trigger:hover,
.table-pagination__size-trigger.is-open {
  border-color: rgba(96, 165, 250, 0.5);
  background: rgba(51, 65, 85, 0.8);
}

.table-pagination__size-caret {
  color: #94a3b8;
  font-size: 11px;
}

.table-pagination__size-option {
  width: 100%;
  text-align: left;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #e2e8f0;
  padding: 8px 10px;
  font-size: 12px;
}

.table-pagination__size-option:hover {
  background: rgba(51, 65, 85, 0.75);
}

.table-pagination__size-option.is-active {
  background: rgba(51, 65, 85, 0.95);
  color: #ffffff;
  font-weight: 700;
}

.table-pagination__range,
.table-pagination__page-indicator {
  color: #94a3b8;
  font-size: 12px;
}

.table-pagination__page-indicator {
  min-width: 56px;
  text-align: center;
}

.table-pagination__icon-btn {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 1px solid rgba(71, 85, 105, 0.6);
  background: rgba(15, 23, 42, 0.7);
  color: #cbd5e1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.table-pagination__icon-btn svg {
  width: 14px;
  height: 14px;
  fill: currentColor;
}

.table-pagination__icon-btn:hover:not(:disabled) {
  border-color: rgba(96, 165, 250, 0.5);
  background: rgba(51, 65, 85, 0.8);
}

.table-pagination__icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 760px) {
  .table-pagination {
    align-items: flex-start;
  }

  .table-pagination__right {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
