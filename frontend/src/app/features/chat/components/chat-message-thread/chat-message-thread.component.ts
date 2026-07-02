import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { ChatApiService } from '../../../../core/services/chat-api.service';
import { SipClientService } from '../../../call-control/services/sip-client.service';
import type { ChatAttachment, ChatConversation, ChatConversationCallTarget, ChatMessage, ChatPresenceUser } from '../../models/chat.model';

@Component({
  selector: 'app-chat-message-thread',
  templateUrl: './chat-message-thread.component.html',
  styleUrls: ['./chat-message-thread.component.scss'],
  standalone: true,
  imports: [CommonModule],
})
export class ChatMessageThreadComponent {
  @Input() conversation: ChatConversation | null = null;
  @Input() messages: ChatMessage[] = [];
  @Input() typingUsers: ChatPresenceUser[] = [];
  @Input() currentUserId: number | null = null;
  @Input() loading = false;
  @Input() error: string | null = null;

  constructor(
    private readonly chatApi: ChatApiService,
    private readonly permissionService: PermissionService,
    private readonly sipClient: SipClientService,
  ) {}

  get canViewMessages(): boolean {
    const access = this.conversation?.current_user_access;
    if (!this.conversation) return false;
    if (this.conversation.status === 'deleted') return false;
    if (access?.access_state === 'hidden') return false;
    if (access?.block_display_mode === 'hide_chat') return false;
    if (access?.access_state === 'blocked' && access.block_display_mode === 'show_notice') return false;
    return true;
  }

  get canUseCallControl(): boolean {
    return this.permissionService.hasTenantPermission('call_control.view');
  }

  get isDirectConversation(): boolean {
    return (this.conversation?.type ?? '').toLowerCase() === 'direct';
  }

  get callTarget(): ChatConversationCallTarget | null {
    return this.conversation?.call_target ?? null;
  }

  get showCallButton(): boolean {
    return this.isDirectConversation && this.canUseCallControl;
  }

  get canStartCall(): boolean {
    return Boolean(this.callTarget?.callable && (this.callTarget.sip_uri || this.callTarget.extension_number));
  }

  get callButtonTitle(): string {
    if (!this.showCallButton) {
      return '';
    }

    if (!this.canStartCall) {
      return this.callUnavailableReason;
    }

    const targetLabel = this.callTargetLabel;
    return targetLabel ? `Call ${targetLabel}` : 'Start audio call';
  }

  get callTargetLabel(): string {
    const displayName = this.callTarget?.display_name?.trim();
    const extensionNumber = this.callTarget?.extension_number?.trim();

    if (displayName && extensionNumber) {
      return `${displayName} (${extensionNumber})`;
    }

    if (displayName) {
      return displayName;
    }

    if (extensionNumber) {
      return extensionNumber;
    }

    return 'direct participant';
  }

  get callUnavailableReason(): string {
    if (!this.showCallButton) {
      return 'Call control is unavailable.';
    }

    if (!this.callTarget) {
      return 'No callable participant was resolved for this direct chat.';
    }

    if (this.callTarget.reason?.trim()) {
      return this.callTarget.reason.trim();
    }

    return 'No callable extension is available for this participant.';
  }

  isOwnMessage(message: ChatMessage): boolean {
    return this.currentUserId !== null && message.sender_id === this.currentUserId;
  }

  messageStatusLabel(message: ChatMessage): string {
    if (message.status === 'read') return 'Read';
    if (message.status === 'delivered') return 'Delivered';
    if (message.status === 'sent') return 'Sent';

    const delivery = (message.delivery_status ?? '').toLowerCase();
    if (delivery === 'read') return 'Read';
    if (delivery === 'delivered') return 'Delivered';
    if (delivery === 'sent') return 'Sent';

    return 'Sent';
  }

  readCountLabel(message: ChatMessage): string | null {
    const countRaw = message.read_count ?? message.reads_count;
    const count = typeof countRaw === 'number' ? countRaw : Number(countRaw ?? 0);
    if (!Number.isFinite(count) || count <= 0) {
      return null;
    }
    return `Read by ${count}`;
  }

  attachmentSize(size?: number): string {
    if (!size || size <= 0) {
      return 'Unknown size';
    }

    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  }

  attachmentMimeLabel(mimeType?: string | null): string {
    if (!mimeType) {
      return 'file';
    }
    const [topLevel] = mimeType.split('/');
    return topLevel || 'file';
  }

  isImageAttachment(attachment: ChatAttachment): boolean {
    return (attachment.mime_type ?? '').startsWith('image/');
  }

  attachmentDownloadUrl(attachment: ChatAttachment): string {
    return this.chatApi.getAttachmentDownloadUrl(attachment.id);
  }

  async startAudioCall(): Promise<void> {
    if (!this.canStartCall) {
      return;
    }

    const destination = this.callTarget?.sip_uri?.trim() || this.callTarget?.target?.trim() || this.callTarget?.extension_number?.trim() || '';
    if (!destination) {
      return;
    }

    await this.sipClient.startChatCall(destination);
  }

  get visibleTypingUsers(): ChatPresenceUser[] {
    const deduped: ChatPresenceUser[] = [];
    const seen = new Set<number>();

    this.typingUsers.forEach((user) => {
      if (!user || typeof user.id !== 'number') {
        return;
      }
      if (user.id === this.currentUserId || seen.has(user.id)) {
        return;
      }
      seen.add(user.id);
      deduped.push({
        id: user.id,
        name: (user.name || '').trim() || 'Someone',
        avatar: user.avatar ?? null,
        role: typeof user.role === 'string' ? user.role : undefined,
        device_type: typeof user.device_type === 'string' ? user.device_type : undefined,
      });
    });

    return deduped;
  }

  get typingLabel(): string | null {
    const users = this.visibleTypingUsers;
    if (users.length === 0) return null;
    if (users.length === 1) return `${users[0].name} is typing...`;
    if (users.length === 2) return `${users[0].name} and ${users[1].name} are typing...`;
    return `${users[0].name}, ${users[1].name} and ${users.length - 2} others are typing...`;
  }
}
