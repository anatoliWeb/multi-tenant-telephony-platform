import { realtimeClient } from '../../../shared/services/realtime/realtime.client';

export type ChatAdminRealtimeHandlers = {
  onMessageCreated?: (payload: unknown) => void;
  onMessageUpdated?: (payload: unknown) => void;
  onMessageDeleted?: (payload: unknown) => void;
  onMessageRead?: (payload: unknown) => void;
  onMessageDeviceRead?: (payload: unknown) => void;
  onMessageDeliveryUpdated?: (payload: unknown) => void;
  onParticipantAccessChanged?: (payload: unknown) => void;
  onAttachmentCreated?: (payload: unknown) => void;
  onAttachmentDeleted?: (payload: unknown) => void;
};

const CHAT_EVENTS = {
  messageCreated: '.chat.message.created',
  messageUpdated: '.chat.message.updated',
  messageDeleted: '.chat.message.deleted',
  messageRead: '.chat.message.read',
  messageDeviceRead: '.chat.message.device_read',
  messageDeliveryUpdated: '.chat.message.delivery.updated',
  participantAccessChanged: '.chat.participant.access_changed',
  attachmentCreated: '.chat.attachment.created',
  attachmentDeleted: '.chat.attachment.deleted',
} as const;

class ChatAdminRealtimeService {
  private activeConversationId: number | null = null;
  private unsubscribeActive: (() => void) | null = null;

  subscribeToConversation(conversationId: number, handlers: ChatAdminRealtimeHandlers = {}): void {
    if (this.activeConversationId === conversationId) {
      return;
    }

    this.unsubscribeFromConversation();
    this.activeConversationId = conversationId;
    this.unsubscribeActive = realtimeClient.subscribeToPrivateChannel(
      `chat.conversation.${conversationId}`,
      {
        [CHAT_EVENTS.messageCreated]: (payload: unknown) => handlers.onMessageCreated?.(payload),
        [CHAT_EVENTS.messageUpdated]: (payload: unknown) => handlers.onMessageUpdated?.(payload),
        [CHAT_EVENTS.messageDeleted]: (payload: unknown) => handlers.onMessageDeleted?.(payload),
        [CHAT_EVENTS.messageRead]: (payload: unknown) => handlers.onMessageRead?.(payload),
        [CHAT_EVENTS.messageDeviceRead]: (payload: unknown) => handlers.onMessageDeviceRead?.(payload),
        [CHAT_EVENTS.messageDeliveryUpdated]: (payload: unknown) => handlers.onMessageDeliveryUpdated?.(payload),
        [CHAT_EVENTS.participantAccessChanged]: (payload: unknown) => handlers.onParticipantAccessChanged?.(payload),
        [CHAT_EVENTS.attachmentCreated]: (payload: unknown) => handlers.onAttachmentCreated?.(payload),
        [CHAT_EVENTS.attachmentDeleted]: (payload: unknown) => handlers.onAttachmentDeleted?.(payload),
      },
    );
  }

  unsubscribeFromConversation(): void {
    this.unsubscribeActive?.();
    this.unsubscribeActive = null;
    this.activeConversationId = null;
  }
}

export const chatAdminRealtimeService = new ChatAdminRealtimeService();
