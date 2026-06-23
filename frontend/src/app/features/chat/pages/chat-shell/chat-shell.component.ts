import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SharedModule } from '../../../../shared/shared.module';
import { AuthStateService } from '../../../../core/services/auth-state.service';
import { ChatStateService } from '../../services/chat-state.service';
import type { ChatConversation } from '../../models/chat.model';
import { ChatConversationListComponent } from '../../components/chat-conversation-list/chat-conversation-list.component';
import { ChatMessageThreadComponent } from '../../components/chat-message-thread/chat-message-thread.component';
import { ChatMessageComposerComponent } from '../../components/chat-message-composer/chat-message-composer.component';
import { ChatAccessNoticeComponent } from '../../components/chat-access-notice/chat-access-notice.component';
import { ChatParticipantsPanelComponent } from '../../components/chat-participants-panel/chat-participants-panel.component';

@Component({
  selector: 'app-chat-shell',
  templateUrl: './chat-shell.component.html',
  styleUrls: ['./chat-shell.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule, ChatConversationListComponent, ChatMessageThreadComponent, ChatMessageComposerComponent, ChatAccessNoticeComponent, ChatParticipantsPanelComponent],
})
export class ChatShellComponent implements OnInit, OnDestroy {
  readonly conversations$;
  readonly filteredConversations$;
  readonly activeConversation$;
  readonly messages$;
  readonly participants$;
  readonly presenceUsers$;
  readonly typingUsers$;
  readonly participantsLoading$;
  readonly participantsError$;
  readonly loading$;
  readonly error$;
  readonly sending$;
  readonly currentUserId: number | null;
  readonly conversationSearch$;
  readonly conversationTypeFilter$;
  readonly conversationVisibilityFilter$;
  readonly unreadOnly$;

  selectedConversationId: number | null = null;

  constructor(
    private readonly chatState: ChatStateService,
    private readonly authState: AuthStateService,
  ) {
    this.conversations$ = this.chatState.conversations$;
    this.filteredConversations$ = this.chatState.filteredConversations$;
    this.activeConversation$ = this.chatState.activeConversation$;
    this.messages$ = this.chatState.messages$;
    this.participants$ = this.chatState.participants$;
    this.presenceUsers$ = this.chatState.presenceUsers$;
    this.typingUsers$ = this.chatState.typingUsers$;
    this.participantsLoading$ = this.chatState.participantsLoading$;
    this.participantsError$ = this.chatState.participantsError$;
    this.loading$ = this.chatState.loading$;
    this.error$ = this.chatState.error$;
    this.sending$ = this.chatState.sending$;
    this.conversationSearch$ = this.chatState.conversationSearch$;
    this.conversationTypeFilter$ = this.chatState.conversationTypeFilter$;
    this.conversationVisibilityFilter$ = this.chatState.conversationVisibilityFilter$;
    this.unreadOnly$ = this.chatState.unreadOnly$;
    this.currentUserId = this.authState.userId;
  }

  ngOnInit(): void {
    void this.chatState.loadConversations();
  }

  ngOnDestroy(): void {
    void this.chatState.teardownPresence();
  }

  selectConversation(conversation: ChatConversation): void {
    this.selectedConversationId = conversation.id;
    void this.chatState.openConversation(conversation.id);
  }

  sendMessage(payload: { body: string; file?: File }): void {
    if (payload.file) {
      void this.chatState.sendMessageWithAttachment(payload.body, payload.file);
      return;
    }

    void this.chatState.sendMessage(payload.body);
  }

  canViewThread(conversation: ChatConversation | null): boolean {
    const access = conversation?.current_user_access;
    if (!conversation) return false;
    if (conversation.status === 'deleted') return false;
    if (access?.access_state === 'hidden') return false;
    if (access?.block_display_mode === 'hide_chat') return false;
    if (access?.access_state === 'blocked' && access.block_display_mode === 'show_notice') return false;
    return true;
  }

  canUseComposer(conversation: ChatConversation | null): boolean {
    const access = conversation?.current_user_access;
    if (!conversation) return false;
    if (conversation.status === 'deleted' || conversation.status === 'closed' || conversation.status === 'archived') return false;
    if (access?.access_state === 'hidden') return false;
    if (access?.block_display_mode === 'hide_chat') return false;
    if (access?.access_state === 'blocked') return false;
    if (access?.access_state === 'read_only') return false;
    if (access?.block_display_mode === 'show_read_only_history') return false;
    return true;
  }

  onConversationSearchChange(value: string): void {
    this.chatState.setConversationSearch(value);
  }

  onConversationTypeFilterChange(value: string): void {
    this.chatState.setConversationTypeFilter(value);
  }

  onConversationVisibilityFilterChange(value: string): void {
    this.chatState.setConversationVisibilityFilter(value);
  }

  onConversationUnreadOnlyChange(value: boolean): void {
    this.chatState.setUnreadOnly(value);
  }

  resetConversationFilters(): void {
    this.chatState.resetConversationFilters();
  }

  async createDirectChat(payload: { userId: number }): Promise<void> {
    await this.chatState.createDirectConversation(payload.userId);
  }

  async createGroupChat(payload: { title?: string; participantIds: number[]; visibility: 'private' | 'public' }): Promise<void> {
    await this.chatState.createGroupConversation({
      title: payload.title,
      participant_ids: payload.participantIds,
      visibility: payload.visibility,
    });
  }

  handleTypingStarted(): void {
    void this.chatState.startTyping();
  }

  handleTypingStopped(): void {
    void this.chatState.stopTyping();
  }
}
