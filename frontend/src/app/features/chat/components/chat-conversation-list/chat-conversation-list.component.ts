import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import type { ChatConversation } from '../../models/chat.model';

@Component({
  selector: 'app-chat-conversation-list',
  templateUrl: './chat-conversation-list.component.html',
  styleUrls: ['./chat-conversation-list.component.scss'],
  standalone: true,
  imports: [CommonModule, FormsModule],
})
export class ChatConversationListComponent {
  @Input() conversations: ChatConversation[] = [];
  @Input() totalConversationsCount = 0;
  @Input() selectedConversationId: number | null = null;
  @Input() loading = false;
  @Input() error: string | null = null;
  @Input() search = '';
  @Input() typeFilter = 'all';
  @Input() visibilityFilter = 'all';
  @Input() unreadOnly = false;

  @Output() readonly conversationSelected = new EventEmitter<ChatConversation>();
  @Output() readonly searchChange = new EventEmitter<string>();
  @Output() readonly typeFilterChange = new EventEmitter<string>();
  @Output() readonly visibilityFilterChange = new EventEmitter<string>();
  @Output() readonly unreadOnlyChange = new EventEmitter<boolean>();
  @Output() readonly resetFilters = new EventEmitter<void>();
  @Output() readonly createDirect = new EventEmitter<{ userId: number }>();
  @Output() readonly createGroup = new EventEmitter<{ title?: string; participantIds: number[]; visibility: 'private' | 'public' }>();

  createMode: 'none' | 'direct' | 'group' = 'none';
  directUserId = '';
  groupTitle = '';
  groupParticipantIds = '';
  groupVisibility: 'private' | 'public' = 'private';
  createError: string | null = null;

  selectConversation(conversation: ChatConversation): void {
    this.conversationSelected.emit(conversation);
  }

  typeBadgeLabel(type?: string): string {
    const normalized = (type ?? 'chat').toLowerCase();
    if (['direct', 'group', 'support', 'external', 'system'].includes(normalized)) {
      return normalized;
    }
    return 'chat';
  }

  visibilityBadgeLabel(visibility?: string): string {
    const normalized = (visibility ?? '').toLowerCase();
    if (normalized === 'public') return 'public';
    if (normalized === 'private') return 'private';
    return 'private';
  }

  onSearchInput(value: string): void {
    this.searchChange.emit(value);
  }

  onTypeFilterChange(value: string): void {
    this.typeFilterChange.emit(value);
  }

  onVisibilityFilterChange(value: string): void {
    this.visibilityFilterChange.emit(value);
  }

  onUnreadOnlyChange(value: boolean): void {
    this.unreadOnlyChange.emit(value);
  }

  onResetFilters(): void {
    this.resetFilters.emit();
  }

  openCreate(mode: 'direct' | 'group'): void {
    this.createMode = mode;
    this.createError = null;
  }

  closeCreate(): void {
    this.createMode = 'none';
    this.createError = null;
  }

  submitCreateDirect(): void {
    const userId = Number.parseInt(String(this.directUserId ?? '').trim(), 10);
    if (!Number.isFinite(userId) || userId <= 0) {
      this.createError = 'Enter a valid user id.';
      return;
    }

    this.createError = null;
    this.createDirect.emit({ userId });
    this.directUserId = '';
    this.createMode = 'none';
  }

  submitCreateGroup(): void {
    const participantIds = this.groupParticipantIds
      .split(',')
      .map((item) => Number.parseInt(item.trim(), 10))
      .filter((value) => Number.isFinite(value) && value > 0);

    if (participantIds.length === 0) {
      this.createError = 'Enter at least one participant id.';
      return;
    }

    this.createError = null;
    this.createGroup.emit({
      title: this.groupTitle.trim() || undefined,
      participantIds,
      visibility: this.groupVisibility,
    });
    this.groupTitle = '';
    this.groupParticipantIds = '';
    this.groupVisibility = 'private';
    this.createMode = 'none';
  }
}
