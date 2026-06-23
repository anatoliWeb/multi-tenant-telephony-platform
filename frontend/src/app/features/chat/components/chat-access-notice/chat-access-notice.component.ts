import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import type { ChatConversation } from '../../models/chat.model';

@Component({
  selector: 'app-chat-access-notice',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './chat-access-notice.component.html',
  styleUrls: ['./chat-access-notice.component.scss'],
})
export class ChatAccessNoticeComponent {
  @Input() conversation: ChatConversation | null = null;

  get notice(): string | null {
    const access = this.conversation?.current_user_access;
    const accessState = access?.access_state ?? null;
    const blockMode = access?.block_display_mode ?? null;
    const status = this.conversation?.status ?? null;

    if (accessState === 'hidden' || blockMode === 'hide_chat') {
      return 'This conversation is not available';
    }

    if (accessState === 'blocked' && blockMode === 'show_read_only_history') {
      return 'You can only view previous message history';
    }

    if (accessState === 'blocked' && (blockMode === 'show_notice' || blockMode === null || blockMode === undefined)) {
      return 'You cannot access this conversation';
    }

    if (accessState === 'read_only') {
      return 'Read-only conversation';
    }

    if (status === 'closed' || status === 'archived') {
      return 'Conversation is closed for new messages';
    }

    return null;
  }

  get isBlockedVariant(): boolean {
    const access = this.conversation?.current_user_access;
    const accessState = access?.access_state ?? null;
    const blockMode = access?.block_display_mode ?? null;
    return accessState === 'blocked' || accessState === 'hidden' || blockMode === 'hide_chat';
  }
}

