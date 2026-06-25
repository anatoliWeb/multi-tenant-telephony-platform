<template>
  <section class="chat-admin-page">
    <header class="chat-admin-page__header c-card">
      <div>
        <h2 class="chat-admin-page__title">Chat Monitoring</h2>
        <p class="chat-admin-page__subtitle">Read-only admin visibility over conversations, messages, and participants.</p>
      </div>
    </header>

    <AdminChatFilters
      :search="filters.search"
      :type="filters.type"
      :status="filters.status"
      :visibility="filters.visibility"
      :source="filters.source"
      :unread-only="filters.unreadOnly"
      :assignment="filters.assignment"
      :participant-restriction="filters.participantRestriction"
      :failed-webhook-delivery-only="filters.failedWebhookDeliveryOnly"
      :imported-only="filters.importedOnly"
      @update:search="onSearchChange"
      @update:type="onFilterTypeChange"
      @update:status="onFilterStatusChange"
      @update:visibility="onFilterVisibilityChange"
      @update:source="onFilterSourceChange"
      @update:unread-only="onUnreadOnlyChange"
      @update:assignment="onAssignmentChange"
      @update:participant-restriction="onParticipantRestrictionChange"
      @update:failed-webhook-delivery-only="onFailedWebhookDeliveryOnlyChange"
      @update:imported-only="onImportedOnlyChange"
      @reset="onResetFilters"
    />

    <section class="chat-admin-page__layout">
      <AdminChatConversationList
        :items="conversations"
        :selected-conversation-id="selectedConversationId"
        :loading="isConversationsLoading"
        :error="conversationsError"
        @select="onSelectConversation"
      />

      <section class="chat-admin-page__details">
        <AdminChatConversationDetails
          :conversation="selectedConversation"
          :lifecycle-loading="isConversationLifecycleLoading"
          :action-error="conversationLifecycleError"
          @close="onCloseConversation"
          @archive="onArchiveConversation"
        />
        <AdminChatMessageList
          :items="messages"
          :loading="isMessagesLoading"
          :error="messagesError"
          :action-loading-message-ids="messageActionLoadingIds"
          :action-error="messageActionError"
          @delete="onDeleteMessage"
        />
        <AdminChatReplyComposer
          :conversation="selectedConversation"
          :sending="isReplySending"
          :error="replyError"
          @submit="onReplySubmit"
        />
        <AdminChatParticipantsList
          :items="participants"
          :loading="isParticipantsLoading"
          :error="participantsError"
          :action-loading-user-ids="participantActionLoadingUserIds"
          :action-error="participantActionError"
          @block="onBlockParticipant"
          @unblock="onUnblockParticipant"
          @set-read-only="onSetParticipantReadOnly"
          @restore-full="onRestoreParticipantFullAccess"
          @hide-chat="onHideParticipantChat"
          @show-read-only-history="onShowParticipantReadOnlyHistory"
        />
        <AdminChatWebhookDeliveryStatus
          :items="webhookDeliveries"
          :loading="isWebhookDeliveriesLoading"
          :error="webhookDeliveriesError"
        />
      </section>
    </section>
  </section>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import AdminChatConversationDetails from '../components/AdminChatConversationDetails.vue';
import AdminChatConversationList from '../components/AdminChatConversationList.vue';
import AdminChatFilters from '../components/AdminChatFilters.vue';
import AdminChatMessageList from '../components/AdminChatMessageList.vue';
import AdminChatParticipantsList from '../components/AdminChatParticipantsList.vue';
import AdminChatReplyComposer from '../components/AdminChatReplyComposer.vue';
import AdminChatWebhookDeliveryStatus from '../components/AdminChatWebhookDeliveryStatus.vue';
import { chatAdminService } from '../services/chat-admin.service';
import { chatAdminRealtimeService } from '../services/chat-admin-realtime.service';
import { useTenantStore } from '../../../stores/tenant.store';
import type {
  ChatAdminConversation,
  ChatAdminConversationFilters,
  ChatAdminMessage,
  ChatAdminParticipant,
  ChatAdminWebhookDeliverySummary,
} from '../types/chat-admin.types';

const tenantStore = useTenantStore();
const { activeTenantId } = storeToRefs(tenantStore);

const conversations = ref<ChatAdminConversation[]>([]);
const selectedConversationId = ref<number | null>(null);
const selectedConversation = ref<ChatAdminConversation | null>(null);
const messages = ref<ChatAdminMessage[]>([]);
const participants = ref<ChatAdminParticipant[]>([]);
const webhookDeliveries = ref<ChatAdminWebhookDeliverySummary[]>([]);

const isConversationsLoading = ref(false);
const isMessagesLoading = ref(false);
const isParticipantsLoading = ref(false);
const isWebhookDeliveriesLoading = ref(false);

const conversationsError = ref('');
const messagesError = ref('');
const participantsError = ref('');
const webhookDeliveriesError = ref('');
const isReplySending = ref(false);
const replyError = ref('');
const participantActionLoadingUserIds = ref<number[]>([]);
const participantActionError = ref('');
const isConversationLifecycleLoading = ref(false);
const conversationLifecycleError = ref('');
const messageActionLoadingIds = ref<number[]>([]);
const messageActionError = ref('');

const filters = ref<ChatAdminConversationFilters>({
  search: '',
  type: 'all',
  status: 'all',
  visibility: 'all',
  source: 'all',
  unreadOnly: false,
  assignment: 'all',
  participantRestriction: 'all',
  failedWebhookDeliveryOnly: false,
  importedOnly: false,
});

let searchDebounce: ReturnType<typeof setTimeout> | undefined;
let realtimeMessagesReloadDebounce: ReturnType<typeof setTimeout> | undefined;
let realtimeParticipantsReloadDebounce: ReturnType<typeof setTimeout> | undefined;

const resetTenantScopedState = (): void => {
  chatAdminRealtimeService.unsubscribeFromConversation();
  conversations.value = [];
  selectedConversationId.value = null;
  selectedConversation.value = null;
  messages.value = [];
  participants.value = [];
  webhookDeliveries.value = [];
  isConversationsLoading.value = false;
  isMessagesLoading.value = false;
  isParticipantsLoading.value = false;
  isWebhookDeliveriesLoading.value = false;
  conversationsError.value = '';
  messagesError.value = '';
  participantsError.value = '';
  webhookDeliveriesError.value = '';
  isReplySending.value = false;
  replyError.value = '';
  participantActionLoadingUserIds.value = [];
  participantActionError.value = '';
  isConversationLifecycleLoading.value = false;
  conversationLifecycleError.value = '';
  messageActionLoadingIds.value = [];
  messageActionError.value = '';
};

const listParams = computed<Record<string, string>>(() => {
  const params: Record<string, string> = {};
  if (filters.value.search.trim() !== '') params.search = filters.value.search.trim();
  if (filters.value.type !== 'all') params.type = filters.value.type;
  if (filters.value.status !== 'all') params.status = filters.value.status;
  if (filters.value.visibility !== 'all') params.visibility = filters.value.visibility;
  if (filters.value.source !== 'all') params.source = filters.value.source;
  if (filters.value.unreadOnly) params.unread = 'true';
  if (filters.value.assignment !== 'all') params.assignment = filters.value.assignment;
  if (filters.value.participantRestriction !== 'all') params.participant_restriction = filters.value.participantRestriction;
  if (filters.value.failedWebhookDeliveryOnly) params.failed_webhook_delivery = 'true';
  if (filters.value.importedOnly) params.imported = 'true';
  return params;
});

const loadConversations = async (): Promise<void> => {
  isConversationsLoading.value = true;
  conversationsError.value = '';

  try {
    const response = await chatAdminService.listConversations(listParams.value);
    conversations.value = response.items;

    if (selectedConversationId.value && !conversations.value.some((item) => item.id === selectedConversationId.value)) {
      selectedConversationId.value = null;
      selectedConversation.value = null;
      messages.value = [];
      participants.value = [];
      webhookDeliveries.value = [];
    }
  } catch (error) {
    conversationsError.value = (error as { message?: string })?.message ?? 'Failed to load conversations.';
    conversations.value = [];
  } finally {
    isConversationsLoading.value = false;
  }
};

const loadConversationDetails = async (conversationId: number): Promise<void> => {
  isMessagesLoading.value = true;
  isParticipantsLoading.value = true;
  isWebhookDeliveriesLoading.value = true;
  messagesError.value = '';
  participantsError.value = '';
  webhookDeliveriesError.value = '';

  try {
    const [conversation, nextMessages, nextParticipants, nextWebhookDeliveries] = await Promise.all([
      chatAdminService.getConversation(conversationId),
      chatAdminService.listMessages(conversationId, { per_page: 50 }),
      chatAdminService.listParticipants(conversationId),
      chatAdminService.getConversationWebhookDeliveries(conversationId, { per_page: 25 }),
    ]);

    selectedConversation.value = conversation;
    messages.value = nextMessages;
    participants.value = nextParticipants;
    webhookDeliveries.value = nextWebhookDeliveries;
  } catch (error) {
    const safeMessage = (error as { message?: string })?.message ?? 'Failed to load conversation details.';
    messagesError.value = safeMessage;
    participantsError.value = safeMessage;
    webhookDeliveriesError.value = safeMessage;
    messages.value = [];
    participants.value = [];
    webhookDeliveries.value = [];
  } finally {
    isMessagesLoading.value = false;
    isParticipantsLoading.value = false;
    isWebhookDeliveriesLoading.value = false;
  }
};

const loadMessagesOnly = async (conversationId: number): Promise<void> => {
  isMessagesLoading.value = true;
  messagesError.value = '';
  try {
    messages.value = await chatAdminService.listMessages(conversationId, { per_page: 50 });
  } catch (error) {
    messagesError.value = (error as { message?: string })?.message ?? 'Failed to reload messages.';
  } finally {
    isMessagesLoading.value = false;
  }
};

const onSelectConversation = async (conversationId: number): Promise<void> => {
  chatAdminRealtimeService.unsubscribeFromConversation();
  selectedConversationId.value = conversationId;
  webhookDeliveries.value = [];
  webhookDeliveriesError.value = '';
  await loadConversationDetails(conversationId);
  subscribeRealtimeForSelectedConversation(conversationId);
};

const onSearchChange = (value: string): void => {
  filters.value.search = value;
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    void loadConversations();
  }, 250);
};

const onFilterTypeChange = async (value: string): Promise<void> => {
  filters.value.type = value;
  await loadConversations();
};

const onFilterStatusChange = async (value: string): Promise<void> => {
  filters.value.status = value;
  await loadConversations();
};

const onFilterVisibilityChange = async (value: string): Promise<void> => {
  filters.value.visibility = value;
  await loadConversations();
};

const onFilterSourceChange = async (value: string): Promise<void> => {
  filters.value.source = value;
  await loadConversations();
};

const onUnreadOnlyChange = async (value: boolean): Promise<void> => {
  filters.value.unreadOnly = value;
  await loadConversations();
};

const onAssignmentChange = async (value: 'all' | 'assigned' | 'unassigned'): Promise<void> => {
  filters.value.assignment = value;
  await loadConversations();
};

const onParticipantRestrictionChange = async (value: 'all' | 'blocked' | 'restricted'): Promise<void> => {
  filters.value.participantRestriction = value;
  await loadConversations();
};

const onFailedWebhookDeliveryOnlyChange = async (value: boolean): Promise<void> => {
  filters.value.failedWebhookDeliveryOnly = value;
  await loadConversations();
};

const onImportedOnlyChange = async (value: boolean): Promise<void> => {
  filters.value.importedOnly = value;
  await loadConversations();
};

const onResetFilters = async (): Promise<void> => {
  filters.value = {
    search: '',
    type: 'all',
    status: 'all',
    visibility: 'all',
    source: 'all',
    unreadOnly: false,
    assignment: 'all',
    participantRestriction: 'all',
    failedWebhookDeliveryOnly: false,
    importedOnly: false,
  };
  await loadConversations();
};

const onReplySubmit = async (payload: { body: string; type: 'text' }): Promise<void> => {
  if (!selectedConversationId.value) {
    return;
  }

  isReplySending.value = true;
  replyError.value = '';

  try {
    await chatAdminService.sendMessage(selectedConversationId.value, payload);
    await loadMessagesOnly(selectedConversationId.value);
  } catch (error) {
    replyError.value = (error as { message?: string })?.message ?? 'Failed to send message.';
  } finally {
    isReplySending.value = false;
  }
};

const setMessageActionLoading = (messageId: number, nextState: boolean): void => {
  if (nextState) {
    if (!messageActionLoadingIds.value.includes(messageId)) {
      messageActionLoadingIds.value = [...messageActionLoadingIds.value, messageId];
    }
    return;
  }

  messageActionLoadingIds.value = messageActionLoadingIds.value.filter((id) => id !== messageId);
};

const onDeleteMessage = async (messageId: number): Promise<void> => {
  if (!selectedConversationId.value) return;
  messageActionError.value = '';
  setMessageActionLoading(messageId, true);
  try {
    await chatAdminService.deleteMessage(messageId);
    await loadMessagesOnly(selectedConversationId.value);
  } catch (error) {
    messageActionError.value = (error as { message?: string })?.message ?? 'Failed to delete message.';
  } finally {
    setMessageActionLoading(messageId, false);
  }
};

const reloadSelectedConversationAndList = async (): Promise<void> => {
  if (!selectedConversationId.value) return;
  const [conversation, response] = await Promise.all([
    chatAdminService.getConversation(selectedConversationId.value),
    chatAdminService.listConversations(listParams.value),
  ]);

  selectedConversation.value = conversation;
  conversations.value = response.items;
};

const onCloseConversation = async (): Promise<void> => {
  if (!selectedConversationId.value) return;
  conversationLifecycleError.value = '';
  isConversationLifecycleLoading.value = true;
  try {
    await chatAdminService.closeConversation(selectedConversationId.value);
    await reloadSelectedConversationAndList();
  } catch (error) {
    conversationLifecycleError.value = (error as { message?: string })?.message ?? 'Failed to close conversation.';
  } finally {
    isConversationLifecycleLoading.value = false;
  }
};

const onArchiveConversation = async (): Promise<void> => {
  if (!selectedConversationId.value) return;
  conversationLifecycleError.value = '';
  isConversationLifecycleLoading.value = true;
  try {
    await chatAdminService.archiveConversation(selectedConversationId.value);
    await reloadSelectedConversationAndList();
  } catch (error) {
    conversationLifecycleError.value = (error as { message?: string })?.message ?? 'Failed to archive conversation.';
  } finally {
    isConversationLifecycleLoading.value = false;
  }
};

const setParticipantActionLoading = (participantUserId: number, nextState: boolean): void => {
  if (nextState) {
    if (!participantActionLoadingUserIds.value.includes(participantUserId)) {
      participantActionLoadingUserIds.value = [...participantActionLoadingUserIds.value, participantUserId];
    }
    return;
  }

  participantActionLoadingUserIds.value = participantActionLoadingUserIds.value.filter((id) => id !== participantUserId);
};

const reloadParticipants = async (): Promise<void> => {
  if (!selectedConversationId.value) return;
  try {
    participants.value = await chatAdminService.listParticipants(selectedConversationId.value);
  } catch (error) {
    participantActionError.value = (error as { message?: string })?.message ?? 'Failed to reload participants.';
  }
};

const onBlockParticipant = async (participantUserId: number, blockDisplayMode: 'show_notice' | 'hide_chat' | 'show_read_only_history'): Promise<void> => {
  if (!selectedConversationId.value) return;
  participantActionError.value = '';
  setParticipantActionLoading(participantUserId, true);
  try {
    await chatAdminService.blockParticipant(selectedConversationId.value, participantUserId, {
      block_display_mode: blockDisplayMode,
    });
    await reloadParticipants();
  } catch (error) {
    participantActionError.value = (error as { message?: string })?.message ?? 'Failed to block participant.';
  } finally {
    setParticipantActionLoading(participantUserId, false);
  }
};

const onUnblockParticipant = async (participantUserId: number): Promise<void> => {
  if (!selectedConversationId.value) return;
  participantActionError.value = '';
  setParticipantActionLoading(participantUserId, true);
  try {
    await chatAdminService.unblockParticipant(selectedConversationId.value, participantUserId);
    await reloadParticipants();
  } catch (error) {
    participantActionError.value = (error as { message?: string })?.message ?? 'Failed to unblock participant.';
  } finally {
    setParticipantActionLoading(participantUserId, false);
  }
};

const onSetParticipantReadOnly = async (participantUserId: number): Promise<void> => {
  if (!selectedConversationId.value) return;
  participantActionError.value = '';
  setParticipantActionLoading(participantUserId, true);
  try {
    await chatAdminService.updateParticipantAccess(selectedConversationId.value, participantUserId, {
      access_state: 'read_only',
    });
    await reloadParticipants();
  } catch (error) {
    participantActionError.value = (error as { message?: string })?.message ?? 'Failed to set participant read-only.';
  } finally {
    setParticipantActionLoading(participantUserId, false);
  }
};

const onRestoreParticipantFullAccess = async (participantUserId: number): Promise<void> => {
  if (!selectedConversationId.value) return;
  participantActionError.value = '';
  setParticipantActionLoading(participantUserId, true);
  try {
    await chatAdminService.updateParticipantAccess(selectedConversationId.value, participantUserId, {
      access_state: 'full',
    });
    await reloadParticipants();
  } catch (error) {
    participantActionError.value = (error as { message?: string })?.message ?? 'Failed to restore participant access.';
  } finally {
    setParticipantActionLoading(participantUserId, false);
  }
};

const onHideParticipantChat = async (participantUserId: number): Promise<void> => {
  if (!selectedConversationId.value) return;
  participantActionError.value = '';
  setParticipantActionLoading(participantUserId, true);
  try {
    await chatAdminService.updateParticipantAccess(selectedConversationId.value, participantUserId, {
      access_state: 'hidden',
    });
    await reloadParticipants();
  } catch (error) {
    participantActionError.value = (error as { message?: string })?.message ?? 'Failed to hide participant chat.';
  } finally {
    setParticipantActionLoading(participantUserId, false);
  }
};

const onShowParticipantReadOnlyHistory = async (participantUserId: number): Promise<void> => {
  if (!selectedConversationId.value) return;
  participantActionError.value = '';
  setParticipantActionLoading(participantUserId, true);
  try {
    await chatAdminService.updateParticipantAccess(selectedConversationId.value, participantUserId, {
      access_state: 'blocked',
      block_display_mode: 'show_read_only_history',
    });
    await reloadParticipants();
  } catch (error) {
    participantActionError.value = (error as { message?: string })?.message ?? 'Failed to set read-only history mode.';
  } finally {
    setParticipantActionLoading(participantUserId, false);
  }
};

const scheduleRealtimeMessagesReload = (): void => {
  if (!selectedConversationId.value) return;
  if (realtimeMessagesReloadDebounce) clearTimeout(realtimeMessagesReloadDebounce);
  realtimeMessagesReloadDebounce = setTimeout(() => {
    if (!selectedConversationId.value) return;
    void loadMessagesOnly(selectedConversationId.value);
  }, 300);
};

const scheduleRealtimeParticipantsReload = (): void => {
  if (!selectedConversationId.value) return;
  if (realtimeParticipantsReloadDebounce) clearTimeout(realtimeParticipantsReloadDebounce);
  realtimeParticipantsReloadDebounce = setTimeout(() => {
    void reloadParticipants();
  }, 300);
};

const subscribeRealtimeForSelectedConversation = (conversationId: number): void => {
  chatAdminRealtimeService.subscribeToConversation(conversationId, {
    onMessageCreated: () => scheduleRealtimeMessagesReload(),
    onMessageUpdated: () => scheduleRealtimeMessagesReload(),
    onMessageDeleted: () => scheduleRealtimeMessagesReload(),
    onMessageRead: () => scheduleRealtimeMessagesReload(),
    onMessageDeviceRead: () => scheduleRealtimeMessagesReload(),
    onMessageDeliveryUpdated: () => scheduleRealtimeMessagesReload(),
    onParticipantAccessChanged: () => scheduleRealtimeParticipantsReload(),
    onAttachmentCreated: () => scheduleRealtimeMessagesReload(),
    onAttachmentDeleted: () => scheduleRealtimeMessagesReload(),
  });
};

onMounted(async () => {
  await loadConversations();
});

watch(activeTenantId, async (tenantId, previousTenantId) => {
  if (tenantId === previousTenantId) {
    return;
  }

  resetTenantScopedState();

  if (tenantId) {
    await loadConversations();
  }
});

onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
  if (realtimeMessagesReloadDebounce) clearTimeout(realtimeMessagesReloadDebounce);
  if (realtimeParticipantsReloadDebounce) clearTimeout(realtimeParticipantsReloadDebounce);
  chatAdminRealtimeService.unsubscribeFromConversation();
});
</script>

<style scoped>
.chat-admin-page{display:grid;gap:12px}
.chat-admin-page__header{margin-top:0}
.chat-admin-page__title{margin:0;font-size:18px;color:#f8fafc}
.chat-admin-page__subtitle{margin:6px 0 0;color:#94a3b8;font-size:13px}
.chat-admin-page__layout{display:grid;grid-template-columns:minmax(280px,360px) minmax(0,1fr);gap:12px;align-items:start}
.chat-admin-page__details{display:grid;gap:12px}
@media (max-width:1080px){.chat-admin-page__layout{grid-template-columns:1fr}}
</style>
