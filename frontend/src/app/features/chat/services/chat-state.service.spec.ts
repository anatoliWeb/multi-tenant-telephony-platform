import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { ChatStateService } from './chat-state.service';
import { ChatApiService } from '../../../core/services/chat-api.service';
import { ChatDeviceService } from '../../../core/services/chat-device.service';
import { ChatPresenceClientService } from './chat-presence-client.service';
import { ChatTypingClientService } from './chat-typing-client.service';
import { ChatRealtimeClientService } from './chat-realtime-client.service';

describe('ChatStateService', () => {
  let service: ChatStateService;
  let chatApi: {
    listConversations: ReturnType<typeof vi.fn>;
    getConversation: ReturnType<typeof vi.fn>;
    listMessages: ReturnType<typeof vi.fn>;
    listConversationParticipants: ReturnType<typeof vi.fn>;
    sendMessage: ReturnType<typeof vi.fn>;
    markConversationRead: ReturnType<typeof vi.fn>;
    startTyping: ReturnType<typeof vi.fn>;
    stopTyping: ReturnType<typeof vi.fn>;
    registerDevice: ReturnType<typeof vi.fn>;
    searchMessages: ReturnType<typeof vi.fn>;
    editMessage: ReturnType<typeof vi.fn>;
    deleteMessage: ReturnType<typeof vi.fn>;
    createDirectConversation: ReturnType<typeof vi.fn>;
    createGroupConversation: ReturnType<typeof vi.fn>;
    createPrivateGroupFromDirect: ReturnType<typeof vi.fn>;
    markMessageRead: ReturnType<typeof vi.fn>;
    leavePresence: ReturnType<typeof vi.fn>;
    uploadAttachment: ReturnType<typeof vi.fn>;
    deleteAttachment: ReturnType<typeof vi.fn>;
    registerDeviceOnce: ReturnType<typeof vi.fn>;
    createCallStartedMessage: ReturnType<typeof vi.fn>;
  };
  let chatDevice: {
    ensureRegistered: ReturnType<typeof vi.fn>;
    getDeviceKey: ReturnType<typeof vi.fn>;
    buildRegisterPayload: ReturnType<typeof vi.fn>;
  };
  let chatPresenceClient: {
    joinConversationPresence: ReturnType<typeof vi.fn>;
    leaveConversationPresence: ReturnType<typeof vi.fn>;
  };
  let chatTypingClient: {
    subscribeToTyping: ReturnType<typeof vi.fn>;
    unsubscribeFromTyping: ReturnType<typeof vi.fn>;
    emitTypingStarted: ReturnType<typeof vi.fn>;
    emitTypingStopped: ReturnType<typeof vi.fn>;
  };
  let chatRealtimeClient: {
    subscribeToConversation: ReturnType<typeof vi.fn>;
    unsubscribeFromConversation: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    vi.useRealTimers();
    chatApi = {
      listConversations: vi.fn(),
      getConversation: vi.fn(),
      listMessages: vi.fn(),
      listConversationParticipants: vi.fn(),
      sendMessage: vi.fn(),
      markConversationRead: vi.fn(),
      startTyping: vi.fn(),
      stopTyping: vi.fn(),
      registerDevice: vi.fn(),
      searchMessages: vi.fn(),
      editMessage: vi.fn(),
      deleteMessage: vi.fn(),
      createDirectConversation: vi.fn(),
      createGroupConversation: vi.fn(),
      createPrivateGroupFromDirect: vi.fn(),
      markMessageRead: vi.fn(),
      leavePresence: vi.fn(),
      uploadAttachment: vi.fn(),
      deleteAttachment: vi.fn(),
      registerDeviceOnce: vi.fn(),
      createCallStartedMessage: vi.fn(),
    };

    chatDevice = {
      ensureRegistered: vi.fn(),
      getDeviceKey: vi.fn(),
      buildRegisterPayload: vi.fn(),
    };
    chatPresenceClient = {
      joinConversationPresence: vi.fn(),
      leaveConversationPresence: vi.fn(),
    };
    chatTypingClient = {
      subscribeToTyping: vi.fn(),
      unsubscribeFromTyping: vi.fn(),
      emitTypingStarted: vi.fn().mockResolvedValue(undefined),
      emitTypingStopped: vi.fn().mockResolvedValue(undefined),
    };
    chatRealtimeClient = {
      subscribeToConversation: vi.fn(),
      unsubscribeFromConversation: vi.fn(),
    };

    chatDevice.ensureRegistered.mockResolvedValue(undefined);
    chatDevice.getDeviceKey.mockReturnValue('chatdev_test');
    chatApi.markConversationRead.mockReturnValue(of({ success: true, message: 'ok', data: {} }));
    chatApi.markMessageRead.mockReturnValue(of({ success: true, message: 'ok', data: {} }));

    service = new ChatStateService(
      chatApi as unknown as ChatApiService,
      chatDevice as unknown as ChatDeviceService,
      chatPresenceClient as unknown as ChatPresenceClientService,
      chatTypingClient as unknown as ChatTypingClientService,
      chatRealtimeClient as unknown as ChatRealtimeClientService,
    );
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('loads conversations', async () => {
    chatApi.listConversations.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 1, title: 'A' }],
    }));

    await service.loadConversations();

    let conversationsCount = 0;
    service.conversations$.subscribe((items) => {
      conversationsCount = items.length;
    });
    expect(chatApi.listConversations).toHaveBeenCalled();
    expect(conversationsCount).toBe(1);
  });

  it('opens conversation and loads messages', async () => {
    chatApi.getConversation.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 7, title: 'Room' },
    }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 10, conversation_id: 7, body: 'Hi' }],
    }));
    chatApi.listConversationParticipants.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ user_id: 1, role: 'owner', status: 'active', access_state: 'full' }],
    }));

    await service.openConversation(7);

    let messageCount = 0;
    service.messages$.subscribe((items) => {
      messageCount = items.length;
    });

    expect(chatApi.getConversation).toHaveBeenCalledWith(7);
    expect(chatApi.listMessages).toHaveBeenCalledWith(7, { per_page: 50 });
    expect(chatApi.listConversationParticipants).toHaveBeenCalledWith(7);
    expect(chatPresenceClient.joinConversationPresence).toHaveBeenCalledWith(7, expect.any(Object));
    expect(chatTypingClient.subscribeToTyping).toHaveBeenCalledWith(7, expect.any(Object));
    expect(chatRealtimeClient.subscribeToConversation).toHaveBeenCalledWith(7, expect.any(Object));
    expect(chatDevice.ensureRegistered).toHaveBeenCalled();
    expect(chatApi.markConversationRead).toHaveBeenCalledWith(7, { device_key: 'chatdev_test' });
    expect(messageCount).toBe(1);
  });

  it('handles API error safely', async () => {
    chatApi.listConversations.mockReturnValue(throwError(() => new Error('Boom')));

    await service.loadConversations();

    let errorMessage: string | null = null;
    service.error$.subscribe((value) => {
      errorMessage = value;
    });
    expect(errorMessage).toBe('Boom');
  });

  it('sendMessage appends response once and avoids duplicates', async () => {
    chatApi.getConversation.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 7, title: 'Room' },
    }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [],
    }));
    await service.openConversation(7);

    chatApi.sendMessage.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 99, conversation_id: 7, body: 'Hello' },
    }));

    await service.sendMessage('Hello');
    await service.sendMessage('Hello again');

    let messagesCount = 0;
    service.messages$.subscribe((items) => {
      messagesCount = items.length;
    });
    expect(messagesCount).toBe(1);
  });

  it('markMessageRead sends device_key', async () => {
    chatApi.getConversation.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 7, title: 'Room' },
    }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [],
    }));
    chatApi.listConversationParticipants.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [],
    }));
    chatApi.markConversationRead.mockReturnValue(of({ success: true, message: 'ok', data: {} }));
    chatApi.markMessageRead.mockReturnValue(of({ success: true, message: 'ok', data: {} }));

    await service.openConversation(7);
    await service.markMessageRead(55);

    expect(chatApi.markMessageRead).toHaveBeenCalledWith(55, { device_key: 'chatdev_test' });
  });

  it('no read request without active conversation', async () => {
    chatApi.markConversationRead.mockReturnValue(of({ success: true, message: 'ok', data: {} }));
    await service.markActiveConversationRead();
    expect(chatApi.markConversationRead).not.toHaveBeenCalled();
  });

  it('openConversation does not mark read for hidden conversation', async () => {
    chatApi.getConversation.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: {
        id: 7,
        title: 'Hidden',
        current_user_access: { user_id: 1, access_state: 'hidden' },
      },
    }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [],
    }));
    chatApi.listConversationParticipants.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [],
    }));

    await service.openConversation(7);

    expect(chatApi.markConversationRead).not.toHaveBeenCalled();
  });

  it('send with file calls sendMessage then uploadAttachment', async () => {
    const file = new File(['file-content'], 'demo.txt', { type: 'text/plain' });

    chatApi.getConversation.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 7, title: 'Room' },
    }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [],
    }));
    chatApi.listConversationParticipants.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [],
    }));
    chatApi.sendMessage.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 501, conversation_id: 7, body: 'With file' },
    }));
    chatApi.uploadAttachment.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 9001, message_id: 501 },
    }));

    await service.openConversation(7);
    await service.sendMessageWithAttachment('With file', file);

    expect(chatApi.sendMessage).toHaveBeenCalledWith(7, { body: 'With file', type: 'text' });
    expect(chatApi.uploadAttachment).toHaveBeenCalledWith(501, file);
  });

  it('recordCallStarted persists and upserts a call-started system message', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'Room' } }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [
        { id: 1, conversation_id: 7, body: 'old', type: 'text' },
        { id: 9, conversation_id: 7, body: 'Audio call started', type: 'system' },
      ],
    }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.createCallStartedMessage.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: {
        id: 9,
        conversation_id: 7,
        sender_type: 'system',
        type: 'system',
        body: 'Audio call started',
        metadata: {
          event: 'call_started',
          call_direction: 'outbound',
          initiator_user_id: 1,
          target_user_id: 2,
          target_display_name: 'Tenant A Sales',
          target_extension: '1002',
          started_at: '2026-01-01T00:00:00.000Z',
        },
      },
    }));

    await service.openConversation(7);
    await service.recordCallStarted({
      target_user_id: 2,
      target_display_name: 'Tenant A Sales',
      target_extension: '1002',
    });

    expect(chatApi.createCallStartedMessage).toHaveBeenCalledWith(7, {
      call_direction: 'outbound',
      target_user_id: 2,
      target_display_name: 'Tenant A Sales',
      target_extension: '1002',
    });

    let messages: any[] = [];
    service.messages$.subscribe((items) => {
      messages = items;
    });

    const eventMessage = messages.find((message) => message.id === 9);
    expect(eventMessage?.type).toBe('system');
    expect(eventMessage?.metadata?.event).toBe('call_started');
    expect(eventMessage?.metadata?.target_extension).toBe('1002');
  });

  it('recordCallStarted keeps the timeline stable when the event API fails', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'Room' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.createCallStartedMessage.mockReturnValue(throwError(() => new Error('call event failed')));

    await service.openConversation(7);
    await expect(service.recordCallStarted()).resolves.toBeNull();

    let errorMessage: string | null = null;
    service.error$.subscribe((value) => {
      errorMessage = value;
    });
    expect(errorMessage).toBe('The audio call note could not be saved. The call still started.');
  });

  it('filters conversations by search/type/visibility/unread and reset works', async () => {
    chatApi.listConversations.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [
        { id: 1, title: 'Direct A', type: 'direct', visibility: 'private', unread_count: 0 },
        { id: 2, title: 'Group Ops', type: 'group', visibility: 'public', unread_count: 3 },
      ],
    }));

    await service.loadConversations();

    service.setConversationSearch('group');
    service.setConversationTypeFilter('group');
    service.setConversationVisibilityFilter('public');
    service.setUnreadOnly(true);

    let filtered: any[] = [];
    service.filteredConversations$.subscribe((items) => {
      filtered = items;
    });
    expect(filtered.length).toBe(1);
    expect(filtered[0].id).toBe(2);

    service.resetConversationFilters();
    expect((service as any).conversationSearchSubject.value).toBe('');
    expect((service as any).conversationTypeFilterSubject.value).toBe('all');
    expect((service as any).conversationVisibilityFilterSubject.value).toBe('all');
    expect((service as any).unreadOnlySubject.value).toBe(false);
  });

  it('filters do not call backend per keystroke', async () => {
    chatApi.listConversations.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 1, title: 'General' }],
    }));
    await service.loadConversations();
    expect(chatApi.listConversations).toHaveBeenCalledTimes(1);

    service.setConversationSearch('g');
    service.setConversationSearch('ge');
    service.setConversationSearch('gen');
    service.setConversationTypeFilter('group');
    service.setConversationVisibilityFilter('private');
    service.setUnreadOnly(true);

    expect(chatApi.listConversations).toHaveBeenCalledTimes(1);
  });

  it('switching conversation leaves previous presence and joins new one', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.leavePresence.mockReturnValue(of({ success: true, message: 'ok', data: {} }));

    await service.openConversation(7);

    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 8, title: 'B' } }));
    await service.openConversation(8);

    expect(chatPresenceClient.leaveConversationPresence).toHaveBeenCalled();
    expect(chatApi.leavePresence).toHaveBeenCalledWith(7, { device_key: 'chatdev_test' });
    expect(chatTypingClient.unsubscribeFromTyping).toHaveBeenCalled();
    expect(chatRealtimeClient.unsubscribeFromConversation).toHaveBeenCalled();
    expect(chatPresenceClient.joinConversationPresence).toHaveBeenCalledWith(8, expect.any(Object));
    expect(chatTypingClient.subscribeToTyping).toHaveBeenCalledWith(8, expect.any(Object));
    expect(chatRealtimeClient.subscribeToConversation).toHaveBeenCalledWith(8, expect.any(Object));

    let typingUsers: any[] = [{ id: 1 }];
    service.typingUsers$.subscribe((items) => {
      typingUsers = items;
    });
    expect(typingUsers.length).toBe(0);
  });

  it('teardownPresence leaves active conversation with device_key', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.leavePresence.mockReturnValue(of({ success: true, message: 'ok', data: {} }));

    await service.openConversation(7);
    await service.teardownPresence();

    expect(chatPresenceClient.leaveConversationPresence).toHaveBeenCalled();
    expect(chatApi.leavePresence).toHaveBeenCalledWith(7, { device_key: 'chatdev_test' });
    expect(chatTypingClient.unsubscribeFromTyping).toHaveBeenCalled();
    expect(chatRealtimeClient.unsubscribeFromConversation).toHaveBeenCalled();
  });

  it('presence state handlers set/add/remove users', () => {
    service.setPresenceUsers([{ id: 1, name: 'A' } as any]);
    service.addPresenceUser({ id: 2, name: 'B' } as any);
    service.removePresenceUser(1);

    let users: any[] = [];
    service.presenceUsers$.subscribe((items) => {
      users = items;
    });
    expect(users.length).toBe(1);
    expect(users[0].id).toBe(2);
  });

  it('presence leave errors do not break UI', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.leavePresence.mockReturnValue(throwError(() => new Error('leave failed')));

    await service.openConversation(7);
    await expect(service.teardownPresence()).resolves.toBeUndefined();
  });

  it('startTyping/stopTyping delegates to typing client', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    await service.openConversation(7);

    await service.startTyping();
    await service.stopTyping();

    expect(chatTypingClient.emitTypingStarted).toHaveBeenCalledWith(7);
    expect(chatTypingClient.emitTypingStopped).toHaveBeenCalledWith(7);
  });

  it('clearParticipants clears state', () => {
    (service as any).participantsSubject.next([{ user_id: 7 }]);
    service.clearParticipants();

    let count = -1;
    service.participants$.subscribe((items) => {
      count = items.length;
    });
    expect(count).toBe(0);
  });

  it('created event adds message and does not duplicate existing', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [{ id: 1, conversation_id: 7, body: 'old' }] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatRealtimeClient.subscribeToConversation.mockImplementation((_id: number, h: any) => {
      handlers = h;
    });

    await service.openConversation(7);

    handlers.onMessageCreated({ id: 2, conversation_id: 7, body: 'new', sender_id: 9, metadata: { secret: true } });
    handlers.onMessageCreated({ id: 2, conversation_id: 7, body: 'dup' });
    handlers.onMessageCreated({ id: 3, conversation_id: 99, body: 'other' });

    let messages: any[] = [];
    service.messages$.subscribe((items) => { messages = items; });
    expect(messages.length).toBe(2);
    expect(messages[1].id).toBe(2);
    expect((messages[1] as any).metadata).toBeUndefined();
  });

  it('updated event merges existing message and ignores missing', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [{ id: 1, conversation_id: 7, body: 'old', status: 'sent' }] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatRealtimeClient.subscribeToConversation.mockImplementation((_id: number, h: any) => {
      handlers = h;
    });
    await service.openConversation(7);

    handlers.onMessageUpdated({ id: 1, conversation_id: 7, body: 'edited', edited_at: '2026-01-01T00:00:00Z' });
    handlers.onMessageUpdated({ id: 999, conversation_id: 7, body: 'missing' });

    let messages: any[] = [];
    service.messages$.subscribe((items) => { messages = items; });
    expect(messages.length).toBe(1);
    expect(messages[0].body).toBe('edited');
    expect(messages[0].edited_at).toBe('2026-01-01T00:00:00Z');
  });

  it('deleted event marks message deleted and keeps in list', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [{ id: 1, conversation_id: 7, body: 'old', status: 'sent' }] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatRealtimeClient.subscribeToConversation.mockImplementation((_id: number, h: any) => {
      handlers = h;
    });
    await service.openConversation(7);

    handlers.onMessageDeleted({ message_id: 1, conversation_id: 7, deleted_at: '2026-01-01T00:00:00Z' });

    let messages: any[] = [];
    service.messages$.subscribe((items) => { messages = items; });
    expect(messages.length).toBe(1);
    expect(messages[0].status).toBe('deleted');
    expect(messages[0].body).toBeNull();
  });

  it('message created removes typing user by sender id', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatRealtimeClient.subscribeToConversation.mockImplementation((_id: number, h: any) => {
      handlers = h;
    });
    await service.openConversation(7);
    service.addTypingUser({ id: 77, name: 'Typer' } as any);

    handlers.onMessageCreated({ id: 2, conversation_id: 7, body: 'new', sender_id: 77 });

    let typing: any[] = [];
    service.typingUsers$.subscribe((items) => { typing = items; });
    expect(typing.length).toBe(0);
  });

  it('message.read updates existing message read state', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 11, conversation_id: 7, body: 'm', status: 'sent' }],
    }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatRealtimeClient.subscribeToConversation.mockImplementation((_id: number, h: any) => { handlers = h; });
    await service.openConversation(7);

    handlers.onMessageRead({
      message_id: 11,
      conversation_id: 7,
      status: 'read',
      read_at: '2026-01-01T00:00:00Z',
      reads_count: 2,
    });

    let messages: any[] = [];
    service.messages$.subscribe((items) => { messages = items; });
    expect(messages[0].status).toBe('read');
    expect(messages[0].read_at).toBe('2026-01-01T00:00:00Z');
    expect(messages[0].reads_count).toBe(2);
  });

  it('message.device_read updates only safe fields', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 12, conversation_id: 7, body: 'm', status: 'delivered' }],
    }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatRealtimeClient.subscribeToConversation.mockImplementation((_id: number, h: any) => { handlers = h; });
    await service.openConversation(7);

    handlers.onMessageDeviceRead({
      message_id: 12,
      conversation_id: 7,
      read_at: '2026-01-01T00:00:00Z',
      read_count: 1,
      device_key: 'secret_device',
      user_agent: 'UA',
      ip_address: '127.0.0.1',
      metadata: { secret: true },
    });

    let messages: any[] = [];
    service.messages$.subscribe((items) => { messages = items; });
    expect(messages[0].read_at).toBe('2026-01-01T00:00:00Z');
    expect(messages[0].read_count).toBe(1);
    expect((messages[0] as any).device_key).toBeUndefined();
    expect((messages[0] as any).user_agent).toBeUndefined();
    expect((messages[0] as any).ip_address).toBeUndefined();
    expect((messages[0] as any).metadata).toBeUndefined();
  });

  it('message.delivery.updated updates existing message delivery state', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 13, conversation_id: 7, body: 'm', status: 'sent', delivery_status: 'sent' }],
    }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatRealtimeClient.subscribeToConversation.mockImplementation((_id: number, h: any) => { handlers = h; });
    await service.openConversation(7);

    handlers.onMessageDeliveryUpdated({
      message_id: 13,
      conversation_id: 7,
      status: 'delivered',
      delivery_status: 'delivered',
      delivered_at: '2026-01-01T00:00:00Z',
      user_agent: 'UA',
      metadata: { secret: true },
    });

    let messages: any[] = [];
    service.messages$.subscribe((items) => { messages = items; });
    expect(messages[0].status).toBe('delivered');
    expect(messages[0].delivery_status).toBe('delivered');
    expect(messages[0].delivered_at).toBe('2026-01-01T00:00:00Z');
    expect((messages[0] as any).user_agent).toBeUndefined();
    expect((messages[0] as any).metadata).toBeUndefined();
  });

  it('read/delivery events ignore unknown message and other conversations', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 21, conversation_id: 7, body: 'm', status: 'sent' }],
    }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatRealtimeClient.subscribeToConversation.mockImplementation((_id: number, h: any) => { handlers = h; });
    await service.openConversation(7);

    handlers.onMessageRead({ message_id: 999, conversation_id: 7, status: 'read' });
    handlers.onMessageDeliveryUpdated({ message_id: 21, conversation_id: 999, status: 'delivered' });

    let messages: any[] = [];
    service.messages$.subscribe((items) => { messages = items; });
    expect(messages.length).toBe(1);
    expect(messages[0].status).toBe('sent');
  });

  it('create direct conversation calls API and opens created conversation', async () => {
    chatApi.createDirectConversation.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 44, title: 'Direct' },
    }));
    chatApi.listConversations.mockReturnValue(of({ success: true, message: 'ok', data: [{ id: 44, title: 'Direct' }] }));
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 44, title: 'Direct' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    await service.createDirectConversation(44);

    expect(chatApi.createDirectConversation).toHaveBeenCalledWith({ user_id: 44 });
    expect(chatApi.getConversation).toHaveBeenCalledWith(44);
  });

  it('create group conversation calls API and opens created conversation', async () => {
    chatApi.createGroupConversation.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 45, title: 'Ops Group' },
    }));
    chatApi.listConversations.mockReturnValue(of({ success: true, message: 'ok', data: [{ id: 45, title: 'Ops Group' }] }));
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 45, title: 'Ops Group' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    await service.createGroupConversation({ title: 'Ops Group', participant_ids: [45, 46], visibility: 'public' });

    expect(chatApi.createGroupConversation).toHaveBeenCalledWith({
      title: 'Ops Group',
      participant_ids: [45, 46],
      visibility: 'public',
    });
    expect(chatApi.getConversation).toHaveBeenCalledWith(45);
  });

  it('typing started adds user and timeout fallback removes it', async () => {
    vi.useFakeTimers();
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatTypingClient.subscribeToTyping.mockImplementation((_id: number, h: any) => {
      handlers = h;
    });

    await service.openConversation(7);
    handlers.onStarted({ conversation_id: 7, user_id: 31, name: 'Typer' });

    let typing: any[] = [];
    service.typingUsers$.subscribe((items) => { typing = items; });
    expect(typing.map((item) => item.id)).toEqual([31]);

    vi.advanceTimersByTime(7001);
    expect(typing.length).toBe(0);
  });

  it('repeated typing started refreshes timeout', async () => {
    vi.useFakeTimers();
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({ success: true, message: 'ok', data: [] }));

    let handlers: any;
    chatTypingClient.subscribeToTyping.mockImplementation((_id: number, h: any) => {
      handlers = h;
    });

    await service.openConversation(7);
    handlers.onStarted({ conversation_id: 7, user_id: 41, name: 'Typer' });
    vi.advanceTimersByTime(5000);
    handlers.onStarted({ conversation_id: 7, user_id: 41, name: 'Typer' });

    let typing: any[] = [];
    service.typingUsers$.subscribe((items) => { typing = items; });

    vi.advanceTimersByTime(2500);
    expect(typing.length).toBe(1);

    vi.advanceTimersByTime(5000);
    expect(typing.length).toBe(0);
  });

  it('typing started for blocked or hidden participant is ignored when participant access is known', async () => {
    chatApi.getConversation.mockReturnValue(of({ success: true, message: 'ok', data: { id: 7, title: 'A' } }));
    chatApi.listMessages.mockReturnValue(of({ success: true, message: 'ok', data: [] }));
    chatApi.listConversationParticipants.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [
        { user_id: 51, status: 'blocked', access_state: 'blocked' },
        { user_id: 52, status: 'active', access_state: 'hidden' },
      ],
    }));

    let handlers: any;
    chatTypingClient.subscribeToTyping.mockImplementation((_id: number, h: any) => {
      handlers = h;
    });

    await service.openConversation(7);
    handlers.onStarted({ conversation_id: 7, user_id: 51, name: 'Blocked' });
    handlers.onStarted({ conversation_id: 7, user_id: 52, name: 'Hidden' });

    let typing: any[] = [];
    service.typingUsers$.subscribe((items) => { typing = items; });
    expect(typing.length).toBe(0);
  });
});
