import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import type { ChatConversation } from '../../models/chat.model';

@Component({
  selector: 'app-chat-message-composer',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './chat-message-composer.component.html',
  styleUrls: ['./chat-message-composer.component.scss'],
})
export class ChatMessageComposerComponent {
  @Input() conversation: ChatConversation | null = null;
  @Input() sending = false;
  @Input() error: string | null = null;

  @Output() readonly messageSubmit = new EventEmitter<{ body: string; file?: File }>();
  @Output() readonly typingStarted = new EventEmitter<void>();
  @Output() readonly typingStopped = new EventEmitter<void>();

  draft = '';
  selectedFile: File | null = null;
  private typingActive = false;
  private typingStopTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly typingIdleMs = 1800;

  get trimmedDraft(): string {
    return this.draft.trim();
  }

  get canSend(): boolean {
    if (this.sending) return false;
    if (!this.conversation?.id) return false;
    if (this.trimmedDraft.length === 0) return false;
    if (this.isBlocked || this.isReadOnly || this.isHidden) return false;
    if (this.isShowReadOnlyHistory) return false;
    if (this.isConversationClosedLike) return false;
    return true;
  }

  get canAttach(): boolean {
    if (!this.conversation?.id) return false;
    if (this.isBlocked || this.isReadOnly || this.isHidden || this.isShowReadOnlyHistory || this.isConversationClosedLike) return false;
    if (this.conversation.current_user_access?.can_attach === false) return false;
    return true;
  }

  get isReadOnly(): boolean {
    return this.conversation?.current_user_access?.access_state === 'read_only';
  }

  get isBlocked(): boolean {
    return this.conversation?.current_user_access?.access_state === 'blocked';
  }

  get isHidden(): boolean {
    const access = this.conversation?.current_user_access;
    return access?.access_state === 'hidden' || access?.block_display_mode === 'hide_chat';
  }

  get isShowReadOnlyHistory(): boolean {
    const access = this.conversation?.current_user_access;
    return access?.access_state === 'blocked' && access?.block_display_mode === 'show_read_only_history';
  }

  get isConversationClosedLike(): boolean {
    const status = this.conversation?.status;
    return status === 'closed' || status === 'archived' || status === 'deleted';
  }

  onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Enter') {
      return;
    }

    if (event.shiftKey) {
      return;
    }

    event.preventDefault();
    this.submit();
  }

  onDraftInput(): void {
    if (!this.canEmitTyping) {
      this.forceStopTyping();
      return;
    }

    if (this.trimmedDraft.length === 0) {
      this.forceStopTyping();
      return;
    }

    if (!this.typingActive) {
      this.typingActive = true;
      this.typingStarted.emit();
    }

    this.scheduleStopTyping();
  }

  submit(): void {
    if (!this.canSend) {
      return;
    }

    this.messageSubmit.emit({ body: this.trimmedDraft, file: this.selectedFile ?? undefined });
    this.forceStopTyping();
    this.draft = '';
    this.selectedFile = null;
  }

  onFileSelected(event: Event): void {
    const target = event.target as HTMLInputElement | null;
    const file = target?.files?.[0];
    this.selectedFile = file ?? null;
  }

  clearSelectedFile(fileInput: HTMLInputElement): void {
    this.selectedFile = null;
    fileInput.value = '';
  }

  onBlur(): void {
    this.forceStopTyping();
  }

  get canEmitTyping(): boolean {
    if (!this.conversation?.id) return false;
    if (this.sending) return false;
    if (this.isReadOnly || this.isBlocked || this.isHidden || this.isShowReadOnlyHistory) return false;
    if (this.isConversationClosedLike) return false;
    return true;
  }

  private scheduleStopTyping(): void {
    if (this.typingStopTimer) {
      clearTimeout(this.typingStopTimer);
    }

    this.typingStopTimer = setTimeout(() => {
      this.forceStopTyping();
    }, this.typingIdleMs);
  }

  private forceStopTyping(): void {
    if (this.typingStopTimer) {
      clearTimeout(this.typingStopTimer);
      this.typingStopTimer = null;
    }

    if (this.typingActive) {
      this.typingActive = false;
      this.typingStopped.emit();
    }
  }
}
