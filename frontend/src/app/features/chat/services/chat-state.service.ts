import { Injectable } from '@angular/core';
import { BehaviorSubject, combineLatest, firstValueFrom, map } from 'rxjs';
import { ChatApiService } from '../../../core/services/chat-api.service';
import { ChatDeviceService } from '../../../core/services/chat-device.service';
import { ChatPresenceClientService } from './chat-presence-client.service';
import { ChatTypingClientService } from './chat-typing-client.service';
import { ChatRealtimeClientService } from './chat-realtime-client.service';
import type { ChatConversation, ChatMessage, ChatParticipant, ChatPresenceUser } from '../models/chat.model';

@Injectable({ providedIn: 'root' })
export class ChatStateService {
  private static readonly TYPING_FALLBACK_MS = 7000;
  private readonly conversationsSubject = new BehaviorSubject<ChatConversation[]>([]);
  private readonly activeConversationSubject = new BehaviorSubject<ChatConversation | null>(null);
  private readonly messagesSubject = new BehaviorSubject<ChatMessage[]>([]);
  private readonly participantsSubject = new BehaviorSubject<ChatParticipant[]>([]);
  private readonly participantsLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly participantsErrorSubject = new BehaviorSubject<string | null>(null);
  private readonly loadingSubject = new BehaviorSubject<boolean>(false);
  private readonly sendingSubject = new BehaviorSubject<boolean>(false);
  private readonly errorSubject = new BehaviorSubject<string | null>(null);
  private readonly typingUsersSubject = new BehaviorSubject<ChatPresenceUser[]>([]);
  private readonly presenceUsersSubject = new BehaviorSubject<ChatPresenceUser[]>([]);
  private readonly conversationSearchSubject = new BehaviorSubject<string>('');
  private readonly conversationTypeFilterSubject = new BehaviorSubject<string>('all');
  private readonly conversationVisibilityFilterSubject = new BehaviorSubject<string>('all');
  private readonly unreadOnlySubject = new BehaviorSubject<boolean>(false);
  private readonly typingFallbackTimers = new Map<number, ReturnType<typeof setTimeout>>();

  readonly conversations$ = this.conversationsSubject.asObservable();
  readonly activeConversation$ = this.activeConversationSubject.asObservable();
  readonly messages$ = this.messagesSubject.asObservable();
  readonly participants$ = this.participantsSubject.asObservable();
  readonly participantsLoading$ = this.participantsLoadingSubject.asObservable();
  readonly participantsError$ = this.participantsErrorSubject.asObservable();
  readonly loading$ = this.loadingSubject.asObservable();
  readonly sending$ = this.sendingSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();
  readonly typingUsers$ = this.typingUsersSubject.asObservable();
  readonly presenceUsers$ = this.presenceUsersSubject.asObservable();
  readonly conversationSearch$ = this.conversationSearchSubject.asObservable();
  readonly conversationTypeFilter$ = this.conversationTypeFilterSubject.asObservable();
  readonly conversationVisibilityFilter$ = this.conversationVisibilityFilterSubject.asObservable();
  readonly unreadOnly$ = this.unreadOnlySubject.asObservable();
  readonly filteredConversations$ = combineLatest([
    this.conversations$,
    this.conversationSearch$,
    this.conversationTypeFilter$,
    this.conversationVisibilityFilter$,
    this.unreadOnly$,
  ]).pipe(
    map(([conversations, search, typeFilter, visibilityFilter, unreadOnly]) => {
      const normalizedSearch = search.trim().toLowerCase();
      return conversations.filter((conversation) => {
        if (typeFilter !== 'all' && (conversation.type ?? '').toLowerCase() !== typeFilter) {
          return false;
        }

        if (visibilityFilter !== 'all' && (conversation.visibility ?? '').toLowerCase() !== visibilityFilter) {
          return false;
        }

        if (unreadOnly && (conversation.unread_count ?? 0) <= 0) {
          return false;
        }

        if (normalizedSearch.length > 0) {
          const haystack = [
            conversation.title ?? '',
            conversation.description ?? '',
            conversation.type ?? '',
            conversation.source ?? '',
          ].join(' ').toLowerCase();

          if (!haystack.includes(normalizedSearch)) {
            return false;
          }
        }

        return true;
      });
    }),
  );

  constructor(
    private readonly chatApi: ChatApiService,
    private readonly chatDevice: ChatDeviceService,
    private readonly chatPresenceClient: ChatPresenceClientService,
    private readonly chatTypingClient: ChatTypingClientService,
    private readonly chatRealtimeClient: ChatRealtimeClientService,
  ) {}

  async loadConversations(params?: Record<string, string | number | boolean>): Promise<void> {
    this.loadingSubject.next(true);
    this.errorSubject.next(null);
    try {
      await this.chatDevice.ensureRegistered(this.chatApi);
      const response = await firstValueFrom(this.chatApi.listConversations(params));
      this.conversationsSubject.next(Array.isArray(response.data) ? response.data : []);
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to load conversations.'));
    } finally {
      this.loadingSubject.next(false);
    }
  }

  async openConversation(conversationId: number): Promise<void> {
    this.loadingSubject.next(true);
    this.errorSubject.next(null);
    const previousConversationId = this.activeConversationSubject.value?.id ?? null;
    try {
      await this.chatDevice.ensureRegistered(this.chatApi);
      if (previousConversationId && previousConversationId !== conversationId) {
        this.chatPresenceClient.leaveConversationPresence();
        this.chatTypingClient.unsubscribeFromTyping();
        this.chatRealtimeClient.unsubscribeFromConversation();
        await this.safeLeavePresence(previousConversationId);
        this.clearPresenceUsers();
        this.clearTypingUsers();
      }

      const [conversationResponse, messagesResponse] = await Promise.all([
        firstValueFrom(this.chatApi.getConversation(conversationId)),
        firstValueFrom(this.chatApi.listMessages(conversationId, { per_page: 50 })),
      ]);

      this.activeConversationSubject.next(conversationResponse.data ?? null);
      this.messagesSubject.next(Array.isArray(messagesResponse.data) ? messagesResponse.data : []);
      await this.loadParticipants(conversationId);
      this.joinPresence(conversationId);
      this.subscribeTyping(conversationId);
      this.subscribeRealtimeMessages(conversationId);
      if (this.canMarkConversationRead(conversationResponse.data ?? null)) {
        await this.markActiveConversationRead();
      }
    } catch (error) {
      this.activeConversationSubject.next(null);
      this.clearParticipants();
      this.clearPresenceUsers();
      this.clearTypingUsers();
      this.chatRealtimeClient.unsubscribeFromConversation();
      this.errorSubject.next(this.toSafeError(error, 'Failed to open conversation.'));
    } finally {
      this.loadingSubject.next(false);
    }
  }

  async loadMessages(conversationId: number, params?: Record<string, string | number | boolean>): Promise<void> {
    this.loadingSubject.next(true);
    this.errorSubject.next(null);
    try {
      const response = await firstValueFrom(this.chatApi.listMessages(conversationId, params));
      this.messagesSubject.next(Array.isArray(response.data) ? response.data : []);
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to load messages.'));
    } finally {
      this.loadingSubject.next(false);
    }
  }

  async loadParticipants(conversationId: number): Promise<void> {
    this.participantsLoadingSubject.next(true);
    this.participantsErrorSubject.next(null);
    try {
      const response = await firstValueFrom(this.chatApi.listConversationParticipants(conversationId));
      this.participantsSubject.next(Array.isArray(response.data) ? response.data : []);
    } catch (error) {
      this.participantsErrorSubject.next(this.toSafeError(error, 'Failed to load participants.'));
      this.participantsSubject.next([]);
    } finally {
      this.participantsLoadingSubject.next(false);
    }
  }

  clearParticipants(): void {
    this.participantsSubject.next([]);
    this.participantsLoadingSubject.next(false);
    this.participantsErrorSubject.next(null);
  }

  async sendMessage(body: string): Promise<void> {
    const active = this.activeConversationSubject.value;
    if (!active?.id) {
      return;
    }

    const trimmedBody = body.trim();
    if (trimmedBody.length === 0) {
      return;
    }

    this.errorSubject.next(null);
    this.sendingSubject.next(true);
    try {
      const response = await firstValueFrom(this.chatApi.sendMessage(active.id, { body: trimmedBody, type: 'text' }));
      if (response.data) {
        const exists = this.messagesSubject.value.some((message) => message.id === response.data?.id);
        if (!exists) {
          this.messagesSubject.next([...this.messagesSubject.value, response.data]);
        }
      }
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to send message.'));
    } finally {
      this.sendingSubject.next(false);
    }
  }

  async recordCallStarted(payload?: {
    target_user_id?: number | null;
    target_display_name?: string | null;
    target_extension?: string | null;
  }): Promise<ChatMessage | null> {
    const active = this.activeConversationSubject.value;
    if (!active?.id) {
      return null;
    }
    const activeConversationId = active.id;

    this.errorSubject.next(null);
    try {
      const response = await firstValueFrom(this.chatApi.createCallStartedMessage(activeConversationId, {
        call_direction: 'outbound',
        ...payload,
      }));
      const message = response.data ?? null;
      if (message && this.activeConversationSubject.value?.id === activeConversationId) {
        this.upsertMessage(message);
      }

      return message;
    } catch (error) {
      console.warn('[Chat] unable to persist call-started event', {
        conversationId: active.id,
        error: error instanceof Error ? error.message : String(error),
      });
      this.errorSubject.next('The audio call note could not be saved. The call still started.');
      return null;
    }
  }

  async sendMessageWithAttachment(body: string, file: File): Promise<void> {
    const active = this.activeConversationSubject.value;
    if (!active?.id) {
      return;
    }

    const trimmedBody = body.trim();
    if (trimmedBody.length === 0) {
      return;
    }

    this.errorSubject.next(null);
    this.sendingSubject.next(true);
    try {
      const messageResponse = await firstValueFrom(this.chatApi.sendMessage(active.id, { body: trimmedBody, type: 'text' }));
      const createdMessage = messageResponse.data;

      if (!createdMessage?.id) {
        throw new Error('Failed to create message for attachment upload.');
      }

      const exists = this.messagesSubject.value.some((message) => message.id === createdMessage.id);
      if (!exists) {
        this.messagesSubject.next([...this.messagesSubject.value, createdMessage]);
      }

      await this.uploadAttachment(createdMessage.id, file);
      await this.loadMessages(active.id, { per_page: 50 });
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to upload attachment.'));
    } finally {
      this.sendingSubject.next(false);
    }
  }

  async createDirectConversation(userId: number): Promise<ChatConversation | null> {
    if (!Number.isFinite(userId) || userId <= 0) {
      this.errorSubject.next('Please provide a valid user id.');
      return null;
    }

    this.loadingSubject.next(true);
    this.errorSubject.next(null);
    try {
      const response = await firstValueFrom(this.chatApi.createDirectConversation({ user_id: userId }));
      const created = response.data ?? null;
      await this.loadConversations();
      if (created?.id) {
        await this.openConversation(created.id);
      }
      return created;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to create direct conversation.'));
      return null;
    } finally {
      this.loadingSubject.next(false);
    }
  }

  async createGroupConversation(payload: {
    title?: string | null;
    participant_ids: number[];
    visibility?: 'private' | 'public';
  }): Promise<ChatConversation | null> {
    const participantIds = [...new Set((payload.participant_ids ?? []).filter((id) => Number.isFinite(id) && id > 0))];
    if (participantIds.length === 0) {
      this.errorSubject.next('Please provide at least one participant id.');
      return null;
    }

    this.loadingSubject.next(true);
    this.errorSubject.next(null);
    try {
      const response = await firstValueFrom(this.chatApi.createGroupConversation({
        title: payload.title?.trim() || undefined,
        participant_ids: participantIds,
        visibility: payload.visibility ?? 'private',
      }));
      const created = response.data ?? null;
      await this.loadConversations();
      if (created?.id) {
        await this.openConversation(created.id);
      }
      return created;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to create group conversation.'));
      return null;
    } finally {
      this.loadingSubject.next(false);
    }
  }

  async uploadAttachment(messageId: number, file: File): Promise<void> {
    await firstValueFrom(this.chatApi.uploadAttachment(messageId, file));
  }

  async markActiveConversationRead(): Promise<void> {
    const active = this.activeConversationSubject.value;
    if (!active?.id) {
      return;
    }

    try {
      await this.chatDevice.ensureRegistered(this.chatApi);
      await firstValueFrom(this.chatApi.markConversationRead(active.id, {
        device_key: this.chatDevice.getDeviceKey(),
      }));
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to mark conversation as read.'));
    }
  }

  private canMarkConversationRead(conversation: ChatConversation | null): boolean {
    if (!conversation?.id) {
      return false;
    }

    const access = conversation.current_user_access;
    if (access?.access_state === 'hidden') return false;
    if (access?.block_display_mode === 'hide_chat') return false;
    if (access?.access_state === 'blocked' && access?.block_display_mode === 'show_notice') return false;
    return true;
  }

  async markMessageRead(messageId: number): Promise<void> {
    const active = this.activeConversationSubject.value;
    if (!active?.id || !messageId) {
      return;
    }

    try {
      await this.chatDevice.ensureRegistered(this.chatApi);
      await firstValueFrom(this.chatApi.markMessageRead(messageId, {
        device_key: this.chatDevice.getDeviceKey(),
      }));
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to mark message as read.'));
    }
  }

  async startTyping(): Promise<void> {
    const active = this.activeConversationSubject.value;
    if (!active?.id) {
      return;
    }

    try {
      await this.chatTypingClient.emitTypingStarted(active.id);
    } catch {
      // Typing is best-effort and should not break the screen flow.
    }
  }

  async stopTyping(): Promise<void> {
    const active = this.activeConversationSubject.value;
    if (!active?.id) {
      return;
    }

    try {
      await this.chatTypingClient.emitTypingStopped(active.id);
    } catch {
      // Typing is best-effort and should not break the screen flow.
    }
  }

  async teardownPresence(): Promise<void> {
    const activeId = this.activeConversationSubject.value?.id ?? null;
    this.chatPresenceClient.leaveConversationPresence();
    this.chatTypingClient.unsubscribeFromTyping();
    this.chatRealtimeClient.unsubscribeFromConversation();
    this.clearPresenceUsers();
    this.clearTypingUsers();

    if (!activeId) {
      return;
    }

    await this.safeLeavePresence(activeId);
  }

  resetForTenantChange(): void {
    this.chatPresenceClient.leaveConversationPresence();
    this.chatTypingClient.unsubscribeFromTyping();
    this.chatRealtimeClient.unsubscribeFromConversation();
    this.conversationsSubject.next([]);
    this.activeConversationSubject.next(null);
    this.messagesSubject.next([]);
    this.clearParticipants();
    this.clearPresenceUsers();
    this.clearTypingUsers();
    this.loadingSubject.next(false);
    this.sendingSubject.next(false);
    this.errorSubject.next(null);
  }

  setConversationSearch(value: string): void {
    this.conversationSearchSubject.next(value);
  }

  setConversationTypeFilter(value: string): void {
    this.conversationTypeFilterSubject.next(value);
  }

  setConversationVisibilityFilter(value: string): void {
    this.conversationVisibilityFilterSubject.next(value);
  }

  setUnreadOnly(value: boolean): void {
    this.unreadOnlySubject.next(value);
  }

  resetConversationFilters(): void {
    this.conversationSearchSubject.next('');
    this.conversationTypeFilterSubject.next('all');
    this.conversationVisibilityFilterSubject.next('all');
    this.unreadOnlySubject.next(false);
  }

  setPresenceUsers(users: ChatPresenceUser[]): void {
    const deduped = users.filter((user, idx, arr) => arr.findIndex((item) => item.id === user.id) === idx);
    this.presenceUsersSubject.next(deduped);
  }

  addPresenceUser(user: ChatPresenceUser): void {
    if (this.presenceUsersSubject.value.some((item) => item.id === user.id)) {
      return;
    }

    this.presenceUsersSubject.next([...this.presenceUsersSubject.value, user]);
  }

  removePresenceUser(userId: number): void {
    this.presenceUsersSubject.next(this.presenceUsersSubject.value.filter((item) => item.id !== userId));
  }

  clearPresenceUsers(): void {
    this.presenceUsersSubject.next([]);
  }

  addTypingUser(user: ChatPresenceUser): void {
    if (!this.isTypingUserAllowed(user.id)) {
      return;
    }

    const safeUser: ChatPresenceUser = {
      id: user.id,
      name: (user.name || '').trim() || 'Someone',
      avatar: user.avatar ?? null,
      role: typeof user.role === 'string' ? user.role : undefined,
      device_type: typeof user.device_type === 'string' ? user.device_type : undefined,
    };

    const hasUser = this.typingUsersSubject.value.some((item) => item.id === safeUser.id);
    if (!hasUser) {
      this.typingUsersSubject.next([...this.typingUsersSubject.value, safeUser]);
    }

    this.scheduleTypingFallback(safeUser.id);
  }

  removeTypingUser(userId: number): void {
    this.clearTypingFallback(userId);
    this.typingUsersSubject.next(this.typingUsersSubject.value.filter((item) => item.id !== userId));
  }

  clearTypingUsers(): void {
    this.clearAllTypingFallbacks();
    this.typingUsersSubject.next([]);
  }

  private toSafeError(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'message' in error) {
      const message = String((error as { message?: unknown }).message ?? '').trim();
      if (message.length > 0) {
        return message;
      }
    }
    return fallback;
  }

  private joinPresence(conversationId: number): void {
    this.chatPresenceClient.joinConversationPresence(conversationId, {
      onHere: (users) => this.setPresenceUsers(users),
      onJoining: (user) => this.addPresenceUser(user),
      onLeaving: (userId) => this.removePresenceUser(userId),
    });
  }

  private async safeLeavePresence(conversationId: number): Promise<void> {
    try {
      await this.chatDevice.ensureRegistered(this.chatApi);
      await firstValueFrom(this.chatApi.leavePresence(conversationId, { device_key: this.chatDevice.getDeviceKey() }));
    } catch {
      // Presence leave is best-effort and should not break UI flow.
    }
  }

  private subscribeTyping(conversationId: number): void {
    this.clearTypingUsers();
    this.chatTypingClient.subscribeToTyping(conversationId, {
      onStarted: (payload) => {
        this.addTypingUser({
          id: payload.user_id,
          name: payload.name,
          role: undefined,
          device_type: payload.device_type,
        });
      },
      onStopped: (payload) => this.removeTypingUser(payload.user_id),
    });
  }

  private scheduleTypingFallback(userId: number): void {
    this.clearTypingFallback(userId);
    const timer = setTimeout(() => {
      this.removeTypingUser(userId);
    }, ChatStateService.TYPING_FALLBACK_MS);
    this.typingFallbackTimers.set(userId, timer);
  }

  private clearTypingFallback(userId: number): void {
    const timer = this.typingFallbackTimers.get(userId);
    if (!timer) {
      return;
    }

    clearTimeout(timer);
    this.typingFallbackTimers.delete(userId);
  }

  private clearAllTypingFallbacks(): void {
    this.typingFallbackTimers.forEach((timer) => clearTimeout(timer));
    this.typingFallbackTimers.clear();
  }

  private isTypingUserAllowed(userId: number): boolean {
    const participant = this.participantsSubject.value.find((item) => item.user_id === userId);
    if (!participant) {
      return true;
    }

    const status = (participant.status ?? '').toLowerCase();
    const accessState = (participant.access_state ?? '').toLowerCase();
    if (status === 'blocked' || status === 'removed' || status === 'left') {
      return false;
    }
    if (accessState === 'hidden' || accessState === 'blocked') {
      return false;
    }

    return true;
  }

  private subscribeRealtimeMessages(conversationId: number): void {
    this.chatRealtimeClient.subscribeToConversation(conversationId, {
      onMessageCreated: (payload) => {
        if (!this.isActiveConversationPayload(payload, conversationId)) {
          return;
        }

        const message = this.sanitizeRealtimeMessage(payload);
        if (!message?.id || !message.conversation_id) {
          return;
        }

        if (this.messagesSubject.value.some((item) => item.id === message.id)) {
          return;
        }

        this.messagesSubject.next([...this.messagesSubject.value, message]);
        if (typeof message.sender_id === 'number') {
          this.removeTypingUser(message.sender_id);
        }
      },
      onMessageUpdated: (payload) => {
        if (!this.isActiveConversationPayload(payload, conversationId)) {
          return;
        }

        const messageId = this.resolvePayloadMessageId(payload);
        if (!messageId) {
          return;
        }

        const patch = this.sanitizeRealtimeMessage(payload);
        this.messagesSubject.next(
          this.messagesSubject.value.map((item) => (item.id === messageId ? { ...item, ...patch, id: messageId } : item)),
        );
      },
      onMessageDeleted: (payload) => {
        if (!this.isActiveConversationPayload(payload, conversationId)) {
          return;
        }

        const messageId = this.resolvePayloadMessageId(payload);
        if (!messageId) {
          return;
        }

        this.messagesSubject.next(
          this.messagesSubject.value.map((item) => (
            item.id === messageId
              ? {
                  ...item,
                  status: 'deleted',
                  body: null,
                  deleted_at: typeof payload.deleted_at === 'string' ? payload.deleted_at : item.deleted_at ?? null,
                }
              : item
          )),
        );
      },
      onMessageRead: (payload) => {
        if (!this.isActiveConversationPayload(payload, conversationId)) {
          return;
        }

        const messageId = this.resolvePayloadMessageId(payload);
        if (!messageId) {
          return;
        }

        if (!this.messagesSubject.value.some((item) => item.id === messageId)) {
          return;
        }

        const safePatch = this.sanitizeRealtimeReadDeliveryPatch(payload);
        this.messagesSubject.next(
          this.messagesSubject.value.map((item) => (
            item.id === messageId ? { ...item, ...safePatch, id: messageId } : item
          )),
        );
      },
      onMessageDeviceRead: (payload) => {
        if (!this.isActiveConversationPayload(payload, conversationId)) {
          return;
        }

        const messageId = this.resolvePayloadMessageId(payload);
        if (!messageId) {
          return;
        }

        if (!this.messagesSubject.value.some((item) => item.id === messageId)) {
          return;
        }

        const safePatch = this.sanitizeRealtimeReadDeliveryPatch(payload);
        this.messagesSubject.next(
          this.messagesSubject.value.map((item) => (
            item.id === messageId ? { ...item, ...safePatch, id: messageId } : item
          )),
        );
      },
      onMessageDeliveryUpdated: (payload) => {
        if (!this.isActiveConversationPayload(payload, conversationId)) {
          return;
        }

        const messageId = this.resolvePayloadMessageId(payload);
        if (!messageId) {
          return;
        }

        if (!this.messagesSubject.value.some((item) => item.id === messageId)) {
          return;
        }

        const safePatch = this.sanitizeRealtimeReadDeliveryPatch(payload);
        this.messagesSubject.next(
          this.messagesSubject.value.map((item) => (
            item.id === messageId ? { ...item, ...safePatch, id: messageId } : item
          )),
        );
      },
    });
  }

  private sanitizeRealtimeMessage(payload: Record<string, unknown>): ChatMessage | null {
    const messageId = this.resolvePayloadMessageId(payload);
    const conversationId = this.resolvePayloadConversationId(payload);
    if (!messageId || !conversationId) {
      return null;
    }

    return {
      id: messageId,
      conversation_id: conversationId,
      sender_id: typeof payload['sender_id'] === 'number' ? payload['sender_id'] as number : null,
      sender_type: typeof payload['sender_type'] === 'string' ? payload['sender_type'] as string : undefined,
      type: typeof payload['type'] === 'string' ? payload['type'] as string : undefined,
      body: typeof payload['body'] === 'string' || payload['body'] === null ? (payload['body'] as string | null) : undefined,
      status: typeof payload['status'] === 'string' ? payload['status'] as string : undefined,
      sent_at: typeof payload['sent_at'] === 'string' ? payload['sent_at'] as string : undefined,
      edited_at: typeof payload['edited_at'] === 'string' ? payload['edited_at'] as string : undefined,
      created_at: typeof payload['created_at'] === 'string' ? payload['created_at'] as string : undefined,
      deleted_at: typeof payload['deleted_at'] === 'string' || payload['deleted_at'] === null
        ? payload['deleted_at'] as string | null
        : undefined,
    };
  }

  private upsertMessage(message: ChatMessage): void {
    const existingIndex = this.messagesSubject.value.findIndex((item) => item.id === message.id);
    if (existingIndex === -1) {
      this.messagesSubject.next([...this.messagesSubject.value, message]);
      return;
    }

    const next = [...this.messagesSubject.value];
    next[existingIndex] = { ...next[existingIndex], ...message };
    this.messagesSubject.next(next);
  }

  private sanitizeRealtimeReadDeliveryPatch(payload: Record<string, unknown>): Partial<ChatMessage> {
    const patch: Partial<ChatMessage> = {};

    if (typeof payload['status'] === 'string') {
      patch.status = payload['status'] as string;
    }

    if (typeof payload['delivery_status'] === 'string' || payload['delivery_status'] === null) {
      patch.delivery_status = payload['delivery_status'] as string | null;
    }

    if (typeof payload['read_at'] === 'string' || payload['read_at'] === null) {
      patch.read_at = payload['read_at'] as string | null;
    }

    if (typeof payload['delivered_at'] === 'string' || payload['delivered_at'] === null) {
      patch.delivered_at = payload['delivered_at'] as string | null;
    }

    if (typeof payload['read_count'] === 'number') {
      patch.read_count = payload['read_count'] as number;
    }

    if (typeof payload['reads_count'] === 'number') {
      patch.reads_count = payload['reads_count'] as number;
    }

    return patch;
  }

  private resolvePayloadMessageId(payload: Record<string, unknown>): number | null {
    const raw = typeof payload['id'] === 'number' ? payload['id'] : payload['message_id'];
    return typeof raw === 'number' ? raw : null;
  }

  private resolvePayloadConversationId(payload: Record<string, unknown>): number | null {
    return typeof payload['conversation_id'] === 'number' ? payload['conversation_id'] as number : null;
  }

  private isActiveConversationPayload(payload: Record<string, unknown>, expectedConversationId: number): boolean {
    const payloadConversationId = this.resolvePayloadConversationId(payload);
    return payloadConversationId === expectedConversationId;
  }
}
