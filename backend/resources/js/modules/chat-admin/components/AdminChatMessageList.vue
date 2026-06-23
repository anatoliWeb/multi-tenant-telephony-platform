<template>
  <section class="chat-admin-messages c-card">
    <h3 class="chat-admin-messages__title">Messages</h3>

    <div v-if="loading" class="chat-admin-messages__state">
      <BaseLoader label="Loading messages..." />
    </div>

    <BaseErrorState
      v-else-if="error"
      title="Failed to load messages"
      :description="error"
    />

    <BaseEmptyState
      v-else-if="items.length === 0"
      title="No messages"
      description="No messages in this conversation."
    />

    <ul v-else class="chat-admin-messages__items">
      <li v-for="message in items" :key="message.id" class="chat-admin-messages__item">
        <div class="chat-admin-messages__head">
          <span>#{{ message.id }}</span>
          <span>{{ message.type || 'unknown' }}</span>
          <span>{{ message.status || 'unknown' }}</span>
          <span>sender: {{ message.sender_id ?? '-' }}</span>
          <span>{{ formatDate(message.sent_at || message.created_at) }}</span>
          <span v-if="message.is_imported" class="chat-admin-messages__badge is-imported">Imported</span>
          <span v-if="isExternalMessage(message)" class="chat-admin-messages__badge is-external">
            External API{{ externalProviderLabel(message) ? `: ${externalProviderLabel(message)}` : '' }}
          </span>
        </div>
        <p class="chat-admin-messages__body">
          {{ message.status === 'deleted' ? 'Message deleted' : (message.body || '-') }}
        </p>
        <div class="chat-admin-messages__actions">
          <p v-if="actionError" class="chat-admin-messages__error">{{ actionError }}</p>
          <button
            v-if="message.status !== 'deleted'"
            :data-testid="`message-delete-${message.id}`"
            type="button"
            class="chat-admin-messages__delete-button"
            :disabled="isDeleteLoading(message.id)"
            @click="onDeleteMessage(message.id)"
          >
            {{ isDeleteLoading(message.id) ? 'Deleting...' : 'Delete' }}
          </button>
          <button
            v-else
            :data-testid="`message-delete-${message.id}`"
            type="button"
            class="chat-admin-messages__delete-button"
            disabled
          >
            Deleted
          </button>
        </div>

        <div v-if="message.is_imported" class="chat-admin-messages__meta-block">
          <h4>Imported history</h4>
          <ul>
            <li v-if="message.imported_from_conversation_id">From conversation: #{{ message.imported_from_conversation_id }}</li>
            <li v-if="message.imported_from_message_id">From message: #{{ message.imported_from_message_id }}</li>
            <li v-if="message.copied_from_message_id">Copied from message: #{{ message.copied_from_message_id }}</li>
            <li v-if="message.import_mode">Import mode: {{ message.import_mode }}</li>
            <li v-if="message.imported_at">Imported at: {{ formatDate(message.imported_at) }}</li>
          </ul>
        </div>

        <div v-if="isExternalMessage(message)" class="chat-admin-messages__meta-block">
          <h4>External source</h4>
          <ul>
            <li v-if="externalProviderLabel(message)">Provider: {{ externalProviderLabel(message) }}</li>
            <li v-if="externalMessageIdLabel(message)">External message id: {{ externalMessageIdLabel(message) }}</li>
            <li v-if="externalDirectionLabel(message)">Direction: {{ externalDirectionLabel(message) }}</li>
            <li v-if="message.source">Source: {{ message.source }}</li>
          </ul>
        </div>

        <div v-if="safeAttachments(message).length > 0" class="chat-admin-messages__meta-block">
          <h4>Attachments</h4>
          <ul class="chat-admin-messages__attachments">
            <li v-for="attachment in safeAttachments(message)" :key="`${message.id}-attachment-${attachment.id}`">
              <div class="chat-admin-messages__attachment-main">
                <span class="chat-admin-messages__attachment-name">{{ attachment.original_name || `attachment-${attachment.id}` }}</span>
                <span class="chat-admin-messages__attachment-badges">
                  <span class="chat-admin-messages__badge">{{ attachment.mime_type || 'file' }}</span>
                  <span class="chat-admin-messages__badge">{{ attachment.status || 'unknown' }}</span>
                  <span v-if="attachment.is_imported" class="chat-admin-messages__badge is-imported">Imported</span>
                </span>
              </div>
              <div class="chat-admin-messages__attachment-meta">
                <span>{{ humanFileSize(attachment.size) }}</span>
                <span>{{ formatDate(attachment.created_at) }}</span>
                <a
                  :href="attachmentDownloadUrl(attachment.id, attachment.download_url)"
                  class="chat-admin-messages__attachment-link"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Download
                </a>
              </div>
            </li>
          </ul>
        </div>

        <div class="chat-admin-messages__monitoring">
          <div class="chat-admin-messages__monitoring-head">
            <strong>Delivery / Read</strong>
            <span
              v-if="isFailedDelivery(message)"
              class="chat-admin-messages__failed-badge"
            >
              failed
            </span>
          </div>

          <ul v-if="hasMonitoringData(message)" class="chat-admin-messages__monitoring-list">
            <li>Status: {{ message.status || '-' }}</li>
            <li>Delivery: {{ message.delivery_status || '-' }}</li>
            <li v-if="message.delivered_at">Delivered at: {{ formatDate(message.delivered_at) }}</li>
            <li v-if="message.read_at">Read at: {{ formatDate(message.read_at) }}</li>
            <li v-if="message.failed_at">Failed at: {{ formatDate(message.failed_at) }}</li>
            <li v-if="resolveReadCount(message) !== null">Read by {{ resolveReadCount(message) }}</li>
            <li v-if="resolveDeliveryCount(message) !== null">Deliveries: {{ resolveDeliveryCount(message) }}</li>
            <li v-if="resolveDeviceReadCount(message) !== null">Device reads: {{ resolveDeviceReadCount(message) }}</li>
            <li v-if="message.read_source">Read source: {{ message.read_source }}</li>
          </ul>
          <p v-else class="chat-admin-messages__monitoring-empty">No delivery/read data</p>

          <div v-if="safeDeviceReads(message).length > 0" class="chat-admin-messages__device-reads">
            <h4>Per-device reads (safe)</h4>
            <ul>
              <li v-for="(row, index) in safeDeviceReads(message)" :key="`${message.id}-device-read-${index}`">
                user: {{ row.user_id ?? '-' }}, device: {{ row.device_type || '-' }}, read_at: {{ formatDate(row.read_at) }}
              </li>
            </ul>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import BaseEmptyState from '../../../shared/components/ui/BaseEmptyState.vue';
import BaseErrorState from '../../../shared/components/ui/BaseErrorState.vue';
import BaseLoader from '../../../shared/components/ui/BaseLoader.vue';
import type {
  ChatAdminAttachmentItem,
  ChatAdminMessage,
  ChatAdminMessageDeviceReadItem,
} from '../types/chat-admin.types';

const props = defineProps<{
  items: ChatAdminMessage[];
  loading: boolean;
  error: string;
  actionLoadingMessageIds?: number[];
  actionError?: string;
}>();

const emit = defineEmits<{
  delete: [messageId: number];
}>();

const isDeleteLoading = (messageId: number): boolean => {
  return props.actionLoadingMessageIds?.includes(messageId) ?? false;
};

const onDeleteMessage = (messageId: number): void => {
  if (isDeleteLoading(messageId)) return;
  if (!window.confirm('Delete this message?')) return;
  emit('delete', messageId);
};

const formatDate = (value: string | null | undefined): string => {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(parsed);
};

const toSafeCount = (value: unknown): number | null => {
  if (typeof value === 'number' && Number.isFinite(value) && value >= 0) return value;
  return null;
};

const resolveReadCount = (message: ChatAdminMessage): number | null => {
  const direct = toSafeCount(message.read_count ?? message.reads_count);
  if (direct !== null) return direct;
  if (Array.isArray(message.message_reads)) return message.message_reads.length;
  return null;
};

const resolveDeliveryCount = (message: ChatAdminMessage): number | null => {
  const direct = toSafeCount(message.delivery_count ?? message.deliveries_count);
  if (direct !== null) return direct;
  if (Array.isArray(message.message_deliveries)) return message.message_deliveries.length;
  return null;
};

const resolveDeviceReadCount = (message: ChatAdminMessage): number | null => {
  const direct = toSafeCount(message.device_read_count);
  if (direct !== null) return direct;
  if (Array.isArray(message.device_reads)) return message.device_reads.length;
  return null;
};

const hasMonitoringData = (message: ChatAdminMessage): boolean => {
  return Boolean(
    message.status
      || message.delivery_status
      || message.delivered_at
      || message.read_at
      || message.failed_at
      || message.read_source
      || resolveReadCount(message) !== null
      || resolveDeliveryCount(message) !== null
      || resolveDeviceReadCount(message) !== null,
  );
};

const isFailedDelivery = (message: ChatAdminMessage): boolean => {
  const status = String(message.delivery_status ?? message.status ?? '').toLowerCase();
  return status.includes('fail') || Boolean(message.failed_at);
};

const safeDeviceReads = (message: ChatAdminMessage): ChatAdminMessageDeviceReadItem[] => {
  if (!Array.isArray(message.device_reads)) return [];
  return message.device_reads.map((row) => ({
    user_id: row.user_id,
    read_at: row.read_at ?? null,
    device_type: row.device_type ?? null,
  }));
};

const isExternalMessage = (message: ChatAdminMessage): boolean => {
  return Boolean(
    message.source === 'api'
      || message.external_provider
      || message.external_message_id
      || message.external_mapping?.provider
      || message.external_mapping?.external_message_id,
  );
};

const externalProviderLabel = (message: ChatAdminMessage): string | null => {
  return message.external_provider ?? message.external_mapping?.provider ?? null;
};

const externalMessageIdLabel = (message: ChatAdminMessage): string | null => {
  return message.external_message_id ?? message.external_mapping?.external_message_id ?? null;
};

const externalDirectionLabel = (message: ChatAdminMessage): string | null => {
  return message.direction ?? message.external_mapping?.direction ?? null;
};

const safeAttachments = (message: ChatAdminMessage): ChatAdminAttachmentItem[] => {
  if (!Array.isArray(message.attachments)) return [];
  return message.attachments.map((attachment) => ({
    id: attachment.id,
    original_name: attachment.original_name ?? null,
    mime_type: attachment.mime_type ?? null,
    size: attachment.size ?? null,
    status: attachment.status ?? null,
    is_imported: Boolean(attachment.is_imported),
    created_at: attachment.created_at ?? null,
    download_url: attachment.download_url ?? null,
  }));
};

const attachmentDownloadUrl = (attachmentId: number, downloadUrl?: string | null): string => {
  if (downloadUrl && downloadUrl.trim() !== '') {
    return downloadUrl;
  }

  return `/api/v1/chat/attachments/${attachmentId}/download`;
};

const humanFileSize = (size: number | null | undefined): string => {
  if (!size || size <= 0) return 'Unknown size';
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<style scoped>
.chat-admin-messages{margin-top:0;display:grid;gap:10px}
.chat-admin-messages__title{margin:0;color:#f8fafc;font-size:15px}
.chat-admin-messages__state{padding:8px 0}
.chat-admin-messages__items{list-style:none;padding:0;margin:0;display:grid;gap:8px;max-height:45vh;overflow:auto}
.chat-admin-messages__item{border:1px solid rgba(71,85,105,.45);border-radius:8px;background:rgba(15,23,42,.5);padding:8px}
.chat-admin-messages__head{display:flex;gap:6px;flex-wrap:wrap;color:#94a3b8;font-size:11px}
.chat-admin-messages__body{margin:8px 0 0;color:#e2e8f0;font-size:13px;white-space:pre-wrap;word-break:break-word}
.chat-admin-messages__actions{margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.chat-admin-messages__delete-button{border:1px solid rgba(239,68,68,.5);background:rgba(127,29,29,.3);color:#fecaca;border-radius:8px;padding:5px 9px;font-size:11px;cursor:pointer}
.chat-admin-messages__delete-button:disabled{opacity:.6;cursor:not-allowed}
.chat-admin-messages__error{margin:0;color:#fca5a5;font-size:12px}
.chat-admin-messages__badge{font-size:10px;border-radius:999px;padding:2px 7px;border:1px solid rgba(71,85,105,.55);color:#cbd5e1}
.chat-admin-messages__badge.is-imported{border-color:rgba(99,102,241,.55);color:#c7d2fe;background:rgba(49,46,129,.25)}
.chat-admin-messages__badge.is-external{border-color:rgba(20,184,166,.55);color:#99f6e4;background:rgba(17,94,89,.25)}
.chat-admin-messages__meta-block{margin-top:8px;padding-top:8px;border-top:1px solid rgba(71,85,105,.35);display:grid;gap:6px}
.chat-admin-messages__meta-block h4{margin:0;color:#cbd5e1;font-size:12px}
.chat-admin-messages__meta-block ul{list-style:none;padding:0;margin:0;display:grid;gap:4px;color:#94a3b8;font-size:12px}
.chat-admin-messages__attachments{display:grid;gap:8px}
.chat-admin-messages__attachment-main{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.chat-admin-messages__attachment-name{color:#e2e8f0}
.chat-admin-messages__attachment-badges{display:flex;gap:6px;flex-wrap:wrap}
.chat-admin-messages__attachment-meta{display:flex;gap:10px;flex-wrap:wrap;color:#94a3b8}
.chat-admin-messages__attachment-link{color:#67e8f9;text-decoration:none}
.chat-admin-messages__attachment-link:hover{text-decoration:underline}
.chat-admin-messages__monitoring{margin-top:8px;padding-top:8px;border-top:1px solid rgba(71,85,105,.45);display:grid;gap:8px}
.chat-admin-messages__monitoring-head{display:flex;align-items:center;gap:8px;color:#cbd5e1;font-size:12px}
.chat-admin-messages__failed-badge{font-size:10px;border-radius:999px;padding:2px 8px;border:1px solid rgba(239,68,68,.5);background:rgba(127,29,29,.25);color:#fca5a5;text-transform:uppercase}
.chat-admin-messages__monitoring-list{list-style:none;padding:0;margin:0;display:grid;gap:4px;color:#94a3b8;font-size:12px}
.chat-admin-messages__monitoring-empty{margin:0;color:#64748b;font-size:12px}
.chat-admin-messages__device-reads h4{margin:0 0 6px;color:#cbd5e1;font-size:12px}
.chat-admin-messages__device-reads ul{list-style:none;padding:0;margin:0;display:grid;gap:4px;color:#94a3b8;font-size:12px}
</style>
