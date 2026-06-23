import { Injectable } from '@angular/core';
import { Subscription } from 'rxjs';
import {
  ChatRealtimeMessageDeliveryPayload,
  ChatRealtimeMessagePayload,
  ChatRealtimeMessageReadPayload,
  RealtimeService,
} from '../../../realtime/services/realtime.service';

@Injectable({ providedIn: 'root' })
export class ChatRealtimeClientService {
  private currentConversationId: number | null = null;
  private createdSub: Subscription | null = null;
  private updatedSub: Subscription | null = null;
  private deletedSub: Subscription | null = null;
  private readSub: Subscription | null = null;
  private deviceReadSub: Subscription | null = null;
  private deliveryUpdatedSub: Subscription | null = null;

  constructor(private readonly realtime: RealtimeService) {}

  subscribeToConversation(
    conversationId: number,
    handlers: {
      onMessageCreated: (message: ChatRealtimeMessagePayload) => void;
      onMessageUpdated: (message: ChatRealtimeMessagePayload) => void;
      onMessageDeleted: (payload: ChatRealtimeMessagePayload) => void;
      onMessageRead: (payload: ChatRealtimeMessageReadPayload) => void;
      onMessageDeviceRead: (payload: ChatRealtimeMessageReadPayload) => void;
      onMessageDeliveryUpdated: (payload: ChatRealtimeMessageDeliveryPayload) => void;
    },
  ): void {
    if (this.currentConversationId === conversationId) {
      return;
    }

    this.unsubscribeFromConversation();
    this.realtime.connect();
    this.realtime.joinChatMessages(conversationId);

    this.createdSub = this.realtime.observeChatMessageCreated(conversationId).subscribe((payload) => {
      handlers.onMessageCreated(payload);
    });
    this.updatedSub = this.realtime.observeChatMessageUpdated(conversationId).subscribe((payload) => {
      handlers.onMessageUpdated(payload);
    });
    this.deletedSub = this.realtime.observeChatMessageDeleted(conversationId).subscribe((payload) => {
      handlers.onMessageDeleted(payload);
    });
    this.readSub = this.realtime.observeChatMessageRead(conversationId).subscribe((payload) => {
      handlers.onMessageRead(payload);
    });
    this.deviceReadSub = this.realtime.observeChatMessageDeviceRead(conversationId).subscribe((payload) => {
      handlers.onMessageDeviceRead(payload);
    });
    this.deliveryUpdatedSub = this.realtime.observeChatMessageDeliveryUpdated(conversationId).subscribe((payload) => {
      handlers.onMessageDeliveryUpdated(payload);
    });

    this.currentConversationId = conversationId;
  }

  unsubscribeFromConversation(): void {
    if (this.currentConversationId === null) {
      return;
    }

    this.realtime.leaveChatMessages(this.currentConversationId);
    this.createdSub?.unsubscribe();
    this.updatedSub?.unsubscribe();
    this.deletedSub?.unsubscribe();
    this.readSub?.unsubscribe();
    this.deviceReadSub?.unsubscribe();
    this.deliveryUpdatedSub?.unsubscribe();
    this.createdSub = null;
    this.updatedSub = null;
    this.deletedSub = null;
    this.readSub = null;
    this.deviceReadSub = null;
    this.deliveryUpdatedSub = null;
    this.currentConversationId = null;
  }
}
