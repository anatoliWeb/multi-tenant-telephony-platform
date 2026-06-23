<template>
  <div class="base-table-wrap">
    <table class="base-table">
      <thead>
        <tr>
          <th
            v-for="column in columns"
            :key="column.key"
            :class="['base-table__th', column.align ? `is-${column.align}` : 'is-left']"
            :style="column.width ? { width: column.width } : undefined"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>

      <tbody v-if="rows.length > 0">
        <tr v-for="(row, index) in rows" :key="resolveRowKey(row, index)" class="base-table__row">
          <td
            v-for="column in columns"
            :key="column.key"
            :class="['base-table__td', column.align ? `is-${column.align}` : 'is-left']"
          >
            <slot :name="`cell:${column.key}`" :row="row" :value="row[column.key]" :index="index">
              {{ row[column.key] ?? '-' }}
            </slot>
          </td>
        </tr>
      </tbody>

      <tbody v-else>
        <tr>
          <td :colspan="columns.length" class="base-table__empty">
            <slot name="empty">No records found.</slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
/**
 * Reusable admin table primitive.
 *
 * WHY THIS EXISTS:
 * CRUD modules should share one table contract (columns + slot cells) so users,
 * roles, tokens, and logs keep consistent scanning patterns and reduce UI drift.
 */
export interface BaseTableColumn {
  key: string;
  label: string;
  width?: string;
  align?: 'left' | 'center' | 'right';
}

interface Props {
  columns: BaseTableColumn[];
  rows: Record<string, unknown>[];
  rowKey?: string;
}

const props = withDefaults(defineProps<Props>(), {
  rowKey: 'id',
});

const resolveRowKey = (row: Record<string, unknown>, index: number): string | number => {
  const candidate = row[props.rowKey];
  return typeof candidate === 'string' || typeof candidate === 'number' ? candidate : index;
};
</script>

<style scoped>
.base-table-wrap {
  overflow-x: auto;
  border: 1px solid rgba(71, 85, 105, 0.5);
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.6);
}

.base-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 980px;
}

.base-table__th {
  padding: 11px 12px;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  font-weight: 700;
  border-bottom: 1px solid rgba(71, 85, 105, 0.5);
}

.base-table__td {
  padding: 12px;
  font-size: 13px;
  color: #e2e8f0;
  border-bottom: 1px solid rgba(51, 65, 85, 0.45);
  vertical-align: middle;
}

.base-table__row:hover {
  background: rgba(30, 41, 59, 0.52);
}

.base-table__empty {
  padding: 24px 12px;
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
}

.is-left {
  text-align: left;
}

.is-center {
  text-align: center;
}

.is-right {
  text-align: right;
}
</style>
