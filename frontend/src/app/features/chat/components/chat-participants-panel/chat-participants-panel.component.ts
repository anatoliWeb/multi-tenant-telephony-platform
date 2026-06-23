import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import type { ChatParticipant, ChatPresenceUser } from '../../models/chat.model';

@Component({
  selector: 'app-chat-participants-panel',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './chat-participants-panel.component.html',
  styleUrls: ['./chat-participants-panel.component.scss'],
})
export class ChatParticipantsPanelComponent {
  @Input() participants: ChatParticipant[] = [];
  @Input() onlineUsers: ChatPresenceUser[] = [];
  @Input() loading = false;
  @Input() error: string | null = null;

  displayName(participant: ChatParticipant): string {
    const nestedName = participant.user?.name?.trim();
    if (nestedName) return nestedName;
    const topLevelName = participant.name?.trim();
    if (topLevelName) return topLevelName;
    return `User #${participant.user_id}`;
  }

  roleLabel(role?: string): string {
    return (role ?? 'member').toLowerCase();
  }

  statusLabel(status?: string): string {
    return (status ?? 'active').toLowerCase();
  }

  accessLabel(accessState?: string): string {
    return (accessState ?? 'full').toLowerCase();
  }

  capabilitySummary(participant: ChatParticipant): string {
    if (participant.access_state === 'read_only') {
      return 'read only';
    }

    const parts: string[] = [];
    if (participant.can_send !== false) parts.push('can send');
    if (participant.can_attach) parts.push('can attach');
    if (parts.length === 0) return 'restricted';
    return parts.join(', ');
  }

  isOnline(participant: ChatParticipant): boolean {
    const participantUserId = participant.user?.id ?? participant.user_id;
    return this.onlineUsers.some((user) => (user as { user_id?: number }).user_id === participantUserId || user.id === participantUserId);
  }

  onlineRole(user: ChatPresenceUser): string {
    return (user.role ?? 'participant').toLowerCase();
  }

  onlineDevice(user: ChatPresenceUser): string {
    return (user.device_type ?? 'unknown').toLowerCase();
  }
}
