import { Injectable } from '@angular/core';
import { Subscription } from 'rxjs';
import { RealtimePresenceUser, RealtimeService } from '../../../realtime/services/realtime.service';

@Injectable({ providedIn: 'root' })
export class ChatPresenceClientService {
  private currentConversationId: number | null = null;
  private presenceSub: Subscription | null = null;

  constructor(private readonly realtime: RealtimeService) {}

  joinConversationPresence(
    conversationId: number,
    handlers: {
      onHere: (users: RealtimePresenceUser[]) => void;
      onJoining: (user: RealtimePresenceUser) => void;
      onLeaving: (userId: number) => void;
    },
  ): void {
    if (this.currentConversationId === conversationId) {
      return;
    }

    this.leaveConversationPresence();
    this.realtime.connect();

    const channel = this.channelName(conversationId);
    let previousUsers: RealtimePresenceUser[] = [];
    this.presenceSub = this.realtime.observePresence(channel).subscribe((users) => {
      const prevIds = new Set(previousUsers.map((item) => item.id));
      const nextIds = new Set(users.map((item) => item.id));

      handlers.onHere(users);

      users.forEach((user) => {
        if (!prevIds.has(user.id)) {
          handlers.onJoining(user);
        }
      });

      previousUsers.forEach((user) => {
        if (!nextIds.has(user.id)) {
          handlers.onLeaving(user.id);
        }
      });

      previousUsers = users;
    });

    this.realtime.joinPresence(channel);
    this.currentConversationId = conversationId;
  }

  leaveConversationPresence(): void {
    if (this.currentConversationId === null) {
      return;
    }

    this.realtime.leavePresence(this.channelName(this.currentConversationId));
    this.presenceSub?.unsubscribe();
    this.presenceSub = null;
    this.currentConversationId = null;
  }

  private channelName(conversationId: number): string {
    return `presence-chat.${conversationId}`;
  }
}

