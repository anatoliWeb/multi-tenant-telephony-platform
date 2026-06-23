<template>
  <section class="chat-admin-webhooks c-card">
    <h3 class="chat-admin-webhooks__title">Webhook delivery status</h3>

    <div v-if="loading" class="chat-admin-webhooks__state">
      <BaseLoader label="Loading webhook deliveries..." />
    </div>

    <BaseErrorState
      v-else-if="error"
      title="Failed to load webhook deliveries"
      :description="error"
    />

    <BaseEmptyState
      v-else-if="items.length === 0"
      title="No webhook deliveries"
      description="No webhook deliveries for this conversation."
    />

    <ul v-else class="chat-admin-webhooks__items">
      <li v-for="item in items" :key="item.id" class="chat-admin-webhooks__item">
        <div class="chat-admin-webhooks__head">
          <strong>#{{ item.id }}</strong>
          <span class="badge">{{ item.event_type || '-' }}</span>
          <span :class="['badge', `is-${normalizeStatus(item.status)}`]">{{ item.status || 'unknown' }}</span>
        </div>
        <ul class="chat-admin-webhooks__meta">
          <li>Attempts: {{ item.attempts ?? 0 }} / {{ item.max_attempts ?? '-' }}</li>
          <li v-if="item.next_retry_at">Next retry: {{ formatDate(item.next_retry_at) }}</li>
          <li v-if="item.last_status_code !== null && item.last_status_code !== undefined">Last status code: {{ item.last_status_code }}</li>
          <li v-if="item.error_summary">Error: {{ item.error_summary }}</li>
          <li v-if="item.endpoint_name">Endpoint: {{ item.endpoint_name }}</li>
          <li v-if="item.endpoint_url">URL: {{ item.endpoint_url }}</li>
          <li v-if="item.sent_at">Sent at: {{ formatDate(item.sent_at) }}</li>
          <li v-if="item.failed_at">Failed at: {{ formatDate(item.failed_at) }}</li>
          <li v-if="item.created_at">Created at: {{ formatDate(item.created_at) }}</li>
        </ul>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import BaseEmptyState from '../../../shared/components/ui/BaseEmptyState.vue';
import BaseErrorState from '../../../shared/components/ui/BaseErrorState.vue';
import BaseLoader from '../../../shared/components/ui/BaseLoader.vue';
import type { ChatAdminWebhookDeliverySummary } from '../types/chat-admin.types';

defineProps<{
  items: ChatAdminWebhookDeliverySummary[];
  loading: boolean;
  error: string;
}>();

const normalizeStatus = (status: string | null | undefined): string => {
  const normalized = String(status ?? '').toLowerCase();
  if (['pending', 'sent', 'retrying', 'failed', 'cancelled'].includes(normalized)) {
    return normalized;
  }

  return 'unknown';
};

const formatDate = (value: string | null | undefined): string => {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(parsed);
};
</script>

<style scoped>
.chat-admin-webhooks{margin-top:0;display:grid;gap:10px}
.chat-admin-webhooks__title{margin:0;color:#f8fafc;font-size:15px}
.chat-admin-webhooks__state{padding:8px 0}
.chat-admin-webhooks__items{list-style:none;padding:0;margin:0;display:grid;gap:8px;max-height:30vh;overflow:auto}
.chat-admin-webhooks__item{border:1px solid rgba(71,85,105,.45);border-radius:8px;background:rgba(15,23,42,.5);padding:8px;display:grid;gap:8px}
.chat-admin-webhooks__head{display:flex;gap:6px;flex-wrap:wrap;align-items:center;color:#e2e8f0}
.chat-admin-webhooks__meta{list-style:none;padding:0;margin:0;display:grid;gap:4px;color:#94a3b8;font-size:12px}
.badge{font-size:10px;border-radius:999px;padding:2px 7px;border:1px solid rgba(71,85,105,.55);color:#cbd5e1}
.badge.is-pending{border-color:rgba(59,130,246,.5);color:#bfdbfe;background:rgba(30,64,175,.2)}
.badge.is-sent{border-color:rgba(16,185,129,.5);color:#bbf7d0;background:rgba(6,78,59,.25)}
.badge.is-retrying{border-color:rgba(245,158,11,.5);color:#fde68a;background:rgba(120,53,15,.25)}
.badge.is-failed{border-color:rgba(239,68,68,.5);color:#fecaca;background:rgba(127,29,29,.25)}
.badge.is-cancelled{border-color:rgba(148,163,184,.5);color:#cbd5e1;background:rgba(51,65,85,.25)}
</style>
