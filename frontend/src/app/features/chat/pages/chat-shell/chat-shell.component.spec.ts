import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { vi } from 'vitest';
import { ChatShellComponent } from './chat-shell.component';
import { ChatStateService } from '../../services/chat-state.service';
import type { ChatConversation, ChatMessage } from '../../models/chat.model';
import { AuthStateService } from '../../../../core/services/auth-state.service';

describe('ChatShellComponent', () => {
  let fixture: ComponentFixture<ChatShellComponent>;
  let component: ChatShellComponent;

  const conversations$ = new BehaviorSubject<ChatConversation[]>([
    { id: 1, title: 'General', type: 'group' },
  ]);
  const activeConversation$ = new BehaviorSubject<ChatConversation | null>(null);
  const messages$ = new BehaviorSubject<ChatMessage[]>([]);
  const loading$ = new BehaviorSubject<boolean>(false);
  const error$ = new BehaviorSubject<string | null>(null);
  const participants$ = new BehaviorSubject<any[]>([]);
  const presenceUsers$ = new BehaviorSubject<any[]>([]);
  const typingUsers$ = new BehaviorSubject<any[]>([]);
  const participantsLoading$ = new BehaviorSubject<boolean>(false);
  const participantsError$ = new BehaviorSubject<string | null>(null);
  const conversationSearch$ = new BehaviorSubject<string>('');
  const conversationTypeFilter$ = new BehaviorSubject<string>('all');
  const conversationVisibilityFilter$ = new BehaviorSubject<string>('all');
  const unreadOnly$ = new BehaviorSubject<boolean>(false);

  const chatStateMock = {
    conversations$,
    filteredConversations$: conversations$,
    activeConversation$,
    messages$,
    loading$,
    error$,
    participants$,
    presenceUsers$,
    typingUsers$,
    participantsLoading$,
    participantsError$,
    conversationSearch$,
    conversationTypeFilter$,
    conversationVisibilityFilter$,
    unreadOnly$,
    loadConversations: vi.fn().mockResolvedValue(undefined),
    openConversation: vi.fn().mockResolvedValue(undefined),
    sendMessage: vi.fn().mockResolvedValue(undefined),
    sendMessageWithAttachment: vi.fn().mockResolvedValue(undefined),
    startTyping: vi.fn().mockResolvedValue(undefined),
    stopTyping: vi.fn().mockResolvedValue(undefined),
    setConversationSearch: vi.fn(),
    setConversationTypeFilter: vi.fn(),
    setConversationVisibilityFilter: vi.fn(),
    setUnreadOnly: vi.fn(),
    resetConversationFilters: vi.fn(),
    createDirectConversation: vi.fn().mockResolvedValue(undefined),
    createGroupConversation: vi.fn().mockResolvedValue(undefined),
    teardownPresence: vi.fn().mockResolvedValue(undefined),
    sending$: new BehaviorSubject<boolean>(false),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ChatShellComponent],
      providers: [
        { provide: ChatStateService, useValue: chatStateMock },
        { provide: AuthStateService, useValue: { userId: 101 } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('shell loads conversations on init', () => {
    expect(chatStateMock.loadConversations).toHaveBeenCalled();
  });

  it('clicking conversation opens it', () => {
    const button: HTMLButtonElement | null = fixture.nativeElement.querySelector('[data-testid="conversation-item"]');
    expect(button).not.toBeNull();
    button?.click();

    expect(chatStateMock.openConversation).toHaveBeenCalledWith(1);
    expect(component.selectedConversationId).toBe(1);
  });

  it('composer submit sends message via state service', () => {
    component.sendMessage({ body: 'Hi' });
    expect(chatStateMock.sendMessage).toHaveBeenCalledWith('Hi');
  });

  it('composer submit with file sends message and attachment via state service', () => {
    const file = new File(['x'], 'demo.txt', { type: 'text/plain' });
    component.sendMessage({ body: 'Hi', file });
    expect(chatStateMock.sendMessageWithAttachment).toHaveBeenCalledWith('Hi', file);
  });

  it('hidden state hides thread messages and composer', () => {
    activeConversation$.next({
      id: 9,
      title: 'Hidden',
      current_user_access: { user_id: 101, access_state: 'hidden' },
    });
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('app-chat-message-thread')).toBeNull();
    expect(fixture.nativeElement.querySelector('app-chat-message-composer')).toBeNull();
    expect(fixture.nativeElement.textContent).toContain('This conversation is not available');
  });

  it('show_read_only_history keeps thread visible and hides composer', () => {
    activeConversation$.next({
      id: 10,
      title: 'History',
      current_user_access: {
        user_id: 101,
        access_state: 'blocked',
        block_display_mode: 'show_read_only_history',
      },
    });
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('app-chat-message-thread')).not.toBeNull();
    expect(fixture.nativeElement.querySelector('app-chat-message-composer')).toBeNull();
    expect(fixture.nativeElement.textContent).toContain('You can only view previous message history');
  });

  it('shell includes participants panel for active conversation', () => {
    activeConversation$.next({ id: 11, title: 'Room' });
    participants$.next([{ user_id: 101, role: 'owner', status: 'active', access_state: 'full' }]);
    presenceUsers$.next([{ id: 101, name: 'Owner', role: 'owner', device_type: 'browser' }]);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('app-chat-participants-panel')).not.toBeNull();
    expect(fixture.nativeElement.textContent).toContain('Participants');
  });

  it('destroy leaves presence safely', () => {
    component.ngOnDestroy();
    expect(chatStateMock.teardownPresence).toHaveBeenCalled();
  });

  it('typing handlers delegate to state service', () => {
    chatStateMock.startTyping = vi.fn().mockResolvedValue(undefined);
    chatStateMock.stopTyping = vi.fn().mockResolvedValue(undefined);
    component.handleTypingStarted();
    component.handleTypingStopped();
    expect(chatStateMock.startTyping).toHaveBeenCalled();
    expect(chatStateMock.stopTyping).toHaveBeenCalled();
  });

  it('create chat actions delegate to state service', async () => {
    await component.createDirectChat({ userId: 22 });
    await component.createGroupChat({ title: 'Ops', participantIds: [22, 33], visibility: 'private' });

    expect(chatStateMock.createDirectConversation).toHaveBeenCalledWith(22);
    expect(chatStateMock.createGroupConversation).toHaveBeenCalledWith({
      title: 'Ops',
      participant_ids: [22, 33],
      visibility: 'private',
    });
  });

  it('filters do not change selected conversation automatically', () => {
    component.selectedConversationId = 1;
    component.onConversationSearchChange('support');
    component.onConversationTypeFilterChange('group');
    component.onConversationVisibilityFilterChange('private');
    component.onConversationUnreadOnlyChange(true);

    expect(chatStateMock.setConversationSearch).toHaveBeenCalledWith('support');
    expect(chatStateMock.setConversationTypeFilter).toHaveBeenCalledWith('group');
    expect(chatStateMock.setConversationVisibilityFilter).toHaveBeenCalledWith('private');
    expect(chatStateMock.setUnreadOnly).toHaveBeenCalledWith(true);
    expect(component.selectedConversationId).toBe(1);
  });
});
