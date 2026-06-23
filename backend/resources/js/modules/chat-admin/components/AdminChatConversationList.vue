<template>
  <section class="chat-admin-list c-card">
    <h3 class="chat-admin-list__title">Conversations</h3>

    <div v-if="loading" class="chat-admin-list__state">
      <BaseLoader label="Loading conversations..." />
    </div>

    <BaseErrorState
      v-else-if="error"
      title="Failed to load conversations"
      :description="error"
    />

    <BaseEmptyState
      v-else-if="items.length === 0"
      title="No conversations"
      description="No admin-visible conversations match current filters."
    />

    <ul v-else class="chat-admin-list__items">
      <li
        v-for="conversation in items"
        :key="conversation.id"
        :class="['chat-admin-list__item', { 'is-active': selectedConversationId === conversation.id }]"
      >
        <button type="button" class="chat-admin-list__button" @click="$emit('select', conversation.id)">
          <div class="chat-admin-list__item-head">
            <strong>{{ conversation.title || `Conversation #${conversation.id}` }}</strong>
            <span class="chat-admin-list__id">#{{ conversation.id }}</span>
          </div>
          <div class="chat-admin-list__badges">
            <span class="badge">{{ conversation.type || 'unknown' }}</span>
            <span class="badge">{{ conversation.visibility || 'unknown' }}</span>
            <span class="badge">{{ conversation.status || 'unknown' }}</span>
            <span class="badge">{{ conversation.source || 'unknown' }}</span>
            <span v-if="hasUnread(conversation)" class="badge badge--accent">Unread: {{ conversation.unread_count }}</span>
            <span v-if="isAssigned(conversation)" class="badge badge--accent">Assigned</span>
            <span v-else-if="isUnassigned(conversation)" class="badge">Unassigned</span>
            <span v-if="hasRestrictedParticipants(conversation)" class="badge badge--warning">Restricted: {{ conversation.restricted_participants_count }}</span>
            <span v-if="hasFailedWebhookDeliveries(conversation)" class="badge badge--danger">Webhook failed: {{ conversation.failed_webhook_deliveries_count }}</span>
            <span v-if="hasImportedMessages(conversation)" class="badge badge--accent">Imported: {{ conversation.imported_messages_count }}</span>
          </div>
          <small class="chat-admin-list__meta">Last: {{ formatDate(conversation.last_message_at) }}</small>
        </button>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import BaseEmptyState from '../../../shared/components/ui/BaseEmptyState.vue';
import BaseErrorState from '../../../shared/components/ui/BaseErrorState.vue';
import BaseLoader from '../../../shared/components/ui/BaseLoader.vue';
import type { ChatAdminConversation } from '../types/chat-admin.types';

defineProps<{
  items: ChatAdminConversation[];
  selectedConversationId: number | null;
  loading: boolean;
  error: string;
}>();

defineEmits<{
  select: [conversationId: number];
}>();

const formatDate = (value: string | null | undefined): string => {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(parsed);
};

const hasUnread = (conversation: ChatAdminConversation): boolean => {
  return typeof conversation.unread_count === 'number' && conversation.unread_count > 0;
};

const isAssigned = (conversation: ChatAdminConversation): boolean => {
  return typeof conversation.assigned_admin_id === 'number' || typeof conversation.assigned_to === 'number';
};

const isUnassigned = (conversation: ChatAdminConversation): boolean => {
  return conversation.assigned_admin_id === null || conversation.assigned_to === null;
};

const hasRestrictedParticipants = (conversation: ChatAdminConversation): boolean => {
  return typeof conversation.restricted_participants_count === 'number' && conversation.restricted_participants_count > 0;
};

const hasFailedWebhookDeliveries = (conversation: ChatAdminConversation): boolean => {
  return typeof conversation.failed_webhook_deliveries_count === 'number' && conversation.failed_webhook_deliveries_count > 0;
};

const hasImportedMessages = (conversation: ChatAdminConversation): boolean => {
  return typeof conversation.imported_messages_count === 'number' && conversation.imported_messages_count > 0;
};
</script>

<style scoped>
.chat-admin-list{margin-top:0;display:grid;gap:10px}
.chat-admin-list__title{margin:0;color:#f8fafc;font-size:15px}
.chat-admin-list__state{padding:10px 0}
.chat-admin-list__items{list-style:none;padding:0;margin:0;display:grid;gap:8px;max-height:70vh;overflow:auto}
.chat-admin-list__item{border:1px solid rgba(71,85,105,.5);border-radius:10px;background:rgba(15,23,42,.6)}
.chat-admin-list__item.is-active{border-color:rgba(96,165,250,.65);box-shadow:0 0 0 2px rgba(59,130,246,.12)}
.chat-admin-list__button{width:100%;text-align:left;background:transparent;border:0;color:#e2e8f0;padding:10px;display:grid;gap:8px}
.chat-admin-list__item-head{display:flex;justify-content:space-between;gap:8px}
.chat-admin-list__id{color:#94a3b8;font-size:11px}
.chat-admin-list__badges{display:flex;gap:6px;flex-wrap:wrap}
.badge{font-size:10px;border-radius:999px;padding:2px 8px;border:1px solid rgba(71,85,105,.55);color:#cbd5e1}
.badge--accent{border-color:rgba(96,165,250,.55);color:#bfdbfe;background:rgba(30,64,175,.2)}
.badge--warning{border-color:rgba(245,158,11,.55);color:#fcd34d;background:rgba(120,53,15,.25)}
.badge--danger{border-color:rgba(239,68,68,.55);color:#fca5a5;background:rgba(127,29,29,.25)}
.chat-admin-list__meta{color:#94a3b8;font-size:11px}
</style>
