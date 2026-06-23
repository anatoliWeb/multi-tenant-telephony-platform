<template>
  <section class="chat-admin-reply c-card">
    <h3 class="chat-admin-reply__title">Admin reply</h3>

    <p v-if="disabledReason" class="chat-admin-reply__hint">{{ disabledReason }}</p>

    <textarea
      data-testid="reply-textarea"
      :value="body"
      class="chat-admin-reply__textarea"
      :disabled="isDisabled"
      placeholder="Type reply message..."
      rows="3"
      @input="onInput"
      @keydown="onKeyDown"
    />

    <p v-if="error" class="chat-admin-reply__error">{{ error }}</p>

    <div class="chat-admin-reply__actions">
      <button
        data-testid="reply-send"
        type="button"
        class="chat-admin-reply__send"
        :disabled="isDisabled || isBodyEmpty"
        @click="onSubmit"
      >
        {{ sending ? 'Sending...' : 'Send' }}
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ChatAdminConversation } from '../types/chat-admin.types';

const props = defineProps<{
  conversation: ChatAdminConversation | null;
  sending: boolean;
  error: string;
}>();

const emit = defineEmits<{
  submit: [payload: { body: string; type: 'text' }];
}>();

const body = ref('');

const isConversationClosed = computed(() => {
  const status = props.conversation?.status;
  return status === 'closed' || status === 'archived' || status === 'deleted';
});

const isReadOnlyAccess = computed(() => {
  const access = props.conversation?.current_user_access;
  if (!access) return false;
  return access.access_state === 'read_only' || access.can_send === false;
});

const isDisabled = computed(() => !props.conversation || props.sending || isConversationClosed.value || isReadOnlyAccess.value);
const isBodyEmpty = computed(() => body.value.trim().length === 0);

const disabledReason = computed(() => {
  if (!props.conversation) return 'Select a conversation to reply.';
  if (isConversationClosed.value) return 'Conversation is closed for new messages.';
  if (isReadOnlyAccess.value) return 'Conversation is read-only.';
  return '';
});

const onInput = (event: Event): void => {
  body.value = (event.target as HTMLTextAreaElement).value;
};

const onSubmit = (): void => {
  if (isDisabled.value || isBodyEmpty.value) return;
  emit('submit', { body: body.value.trim(), type: 'text' });
  body.value = '';
};

const onKeyDown = (event: KeyboardEvent): void => {
  if (event.key !== 'Enter') return;
  if (event.shiftKey) return;
  event.preventDefault();
  onSubmit();
};

watch(
  () => props.conversation?.id,
  () => {
    body.value = '';
  },
);
</script>

<style scoped>
.chat-admin-reply{margin-top:0;display:grid;gap:10px}
.chat-admin-reply__title{margin:0;color:#f8fafc;font-size:15px}
.chat-admin-reply__hint{margin:0;color:#94a3b8;font-size:12px}
.chat-admin-reply__textarea{width:100%;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:10px;font-size:13px;resize:vertical;min-height:84px}
.chat-admin-reply__textarea:focus{outline:none;border-color:rgba(96,165,250,.65);box-shadow:0 0 0 2px rgba(59,130,246,.15)}
.chat-admin-reply__textarea:disabled{opacity:.65;cursor:not-allowed}
.chat-admin-reply__error{margin:0;color:#fca5a5;font-size:12px}
.chat-admin-reply__actions{display:flex;justify-content:flex-end}
.chat-admin-reply__send{height:32px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 12px;font-size:12px}
.chat-admin-reply__send:disabled{opacity:.55;cursor:not-allowed}
</style>

