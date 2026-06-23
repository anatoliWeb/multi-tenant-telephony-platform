import { Injectable } from '@angular/core';
import { firstValueFrom, Subscription } from 'rxjs';
import { ChatTypingPayload, RealtimeService } from '../../../realtime/services/realtime.service';
import { ChatApiService } from '../../../core/services/chat-api.service';
import { ChatDeviceService } from '../../../core/services/chat-device.service';

@Injectable({ providedIn: 'root' })
export class ChatTypingClientService {
  private currentConversationId: number | null = null;
  private sub: Subscription | null = null;

  constructor(
    private readonly realtime: RealtimeService,
    private readonly chatApi: ChatApiService,
    private readonly chatDevice: ChatDeviceService,
  ) {}

  subscribeToTyping(
    conversationId: number,
    handlers: {
      onStarted: (payload: ChatTypingPayload) => void;
      onStopped: (payload: ChatTypingPayload) => void;
    },
  ): void {
    if (this.currentConversationId === conversationId) {
      return;
    }

    this.unsubscribeFromTyping();
    this.realtime.connect();
    this.realtime.joinChatTyping(conversationId);

    this.sub = this.realtime.observeChatTyping(conversationId).subscribe((items) => {
      const nextById = new Map(items.map((item) => [item.user_id, item]));
      const prevById = this.currentSnapshot;

      nextById.forEach((payload, userId) => {
        if (!prevById.has(userId)) {
          handlers.onStarted(payload);
        }
      });

      prevById.forEach((payload, userId) => {
        if (!nextById.has(userId)) {
          handlers.onStopped(payload);
        }
      });

      this.currentSnapshot = nextById;
    });

    this.currentConversationId = conversationId;
    this.currentSnapshot = new Map();
  }

  private currentSnapshot: Map<number, ChatTypingPayload> = new Map();

  unsubscribeFromTyping(): void {
    if (this.currentConversationId === null) {
      return;
    }

    this.realtime.leaveChatTyping(this.currentConversationId);
    this.sub?.unsubscribe();
    this.sub = null;
    this.currentConversationId = null;
    this.currentSnapshot = new Map();
  }

  async emitTypingStarted(conversationId: number): Promise<void> {
    await this.chatDevice.ensureRegistered(this.chatApi);
    await firstValueFrom(this.chatApi.startTyping(conversationId, {
      device_key: this.chatDevice.getDeviceKey(),
      device_type: 'browser',
    }));
  }

  async emitTypingStopped(conversationId: number): Promise<void> {
    await this.chatDevice.ensureRegistered(this.chatApi);
    await firstValueFrom(this.chatApi.stopTyping(conversationId, {
      device_key: this.chatDevice.getDeviceKey(),
      device_type: 'browser',
    }));
  }
}
