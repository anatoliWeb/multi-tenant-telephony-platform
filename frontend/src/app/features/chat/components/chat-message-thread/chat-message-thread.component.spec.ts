import { ComponentFixture, TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { ChatMessageThreadComponent } from './chat-message-thread.component';
import { ChatApiService } from '../../../../core/services/chat-api.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { ChatStateService } from '../../services/chat-state.service';
import { SipClientService } from '../../../call-control/services/sip-client.service';

describe('ChatMessageThreadComponent', () => {
  let fixture: ComponentFixture<ChatMessageThreadComponent>;
  let component: ChatMessageThreadComponent;
  let chatStateMock: { recordCallStarted: ReturnType<typeof vi.fn> };
  let permissionServiceMock: { hasTenantPermission: ReturnType<typeof vi.fn> };
  let sipClientMock: { startChatCall: ReturnType<typeof vi.fn>; canPlaceCall: ReturnType<typeof vi.fn>; callState: string };

  beforeEach(async () => {
    const chatApiMock = {
      getAttachmentDownloadUrl: vi.fn((id: number) => `http://localhost:8080/api/v1/chat/attachments/${id}/download`),
    };
    permissionServiceMock = {
      hasTenantPermission: vi.fn(() => true),
    };
    chatStateMock = {
      recordCallStarted: vi.fn().mockResolvedValue(null),
    };
    sipClientMock = {
      startChatCall: vi.fn().mockResolvedValue(undefined),
      canPlaceCall: vi.fn(() => false),
      callState: 'registered',
    };

    await TestBed.configureTestingModule({
      imports: [ChatMessageThreadComponent],
      providers: [
        { provide: ChatApiService, useValue: chatApiMock },
        { provide: ChatStateService, useValue: chatStateMock },
        { provide: PermissionService, useValue: permissionServiceMock },
        { provide: SipClientService, useValue: sipClientMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatMessageThreadComponent);
    component = fixture.componentInstance;
  });

  it('thread renders messages', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.currentUserId = 5;
    component.messages = [
      { id: 1, conversation_id: 11, sender_id: 5, body: 'Hello', status: 'read', read_count: 2 },
      { id: 2, conversation_id: 11, body: null, status: 'deleted' },
    ];
    fixture.detectChanges();

    const items = fixture.nativeElement.querySelectorAll('[data-testid="message-item"]');
    expect(items.length).toBe(2);
    expect(fixture.nativeElement.textContent).toContain('Message deleted');
    expect(fixture.nativeElement.textContent).toContain('Read');
    expect(fixture.nativeElement.textContent).toContain('Read by 2');
  });

  it('thread renders empty state', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.messages = [];
    component.loading = false;
    component.error = null;
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('No messages yet.');
  });

  it('renders a direct-chat call button when call control permission and target are available', () => {
    component.conversation = {
      id: 11,
      title: 'Room',
      type: 'direct',
      call_target: {
        callable: true,
        display_name: 'Tenant A Sales',
        extension_number: '1002',
        sip_uri: 'sip:1002@localhost',
        target: 'sip:1002@localhost',
      },
    };
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('[data-testid="direct-chat-call-button"]') as HTMLButtonElement;
    expect(button).not.toBeNull();
    expect(button.disabled).toBe(false);
    expect(fixture.nativeElement.textContent).toContain('Tenant A Sales (1002)');
  });

  it('hides the call button when permission is missing', () => {
    permissionServiceMock.hasTenantPermission.mockReturnValue(false);

    component.conversation = {
      id: 11,
      title: 'Room',
      type: 'direct',
      call_target: {
        callable: true,
        display_name: 'Tenant A Sales',
        extension_number: '1002',
        sip_uri: 'sip:1002@localhost',
        target: 'sip:1002@localhost',
      },
    };
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="direct-chat-call-button"]')).toBeNull();
  });

  it('disables the call button and shows a hint when no callable extension is available', () => {
    component.conversation = {
      id: 11,
      title: 'Room',
      type: 'direct',
      call_target: {
        callable: false,
        display_name: 'Tenant A Sales',
        reason: 'The other participant does not have an active callable extension.',
      },
    };
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('[data-testid="direct-chat-call-button"]') as HTMLButtonElement;
    expect(button).not.toBeNull();
    expect(button.disabled).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('The other participant does not have an active callable extension.');
  });

  it('hides the call button for group conversations', () => {
    component.conversation = {
      id: 11,
      title: 'Group',
      type: 'group',
      call_target: {
        callable: true,
        display_name: 'Tenant A Sales',
        extension_number: '1002',
        sip_uri: 'sip:1002@localhost',
        target: 'sip:1002@localhost',
      },
    };
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="direct-chat-call-button"]')).toBeNull();
  });

  it('calls the shared softphone layer with the resolved SIP target when the call button is clicked', async () => {
    component.conversation = {
      id: 11,
      title: 'Room',
      type: 'direct',
      call_target: {
        callable: true,
        display_name: 'Tenant A Sales',
        extension_number: '1002',
        sip_uri: 'sip:1002@localhost',
        target: 'sip:1002@localhost',
      },
    };
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('[data-testid="direct-chat-call-button"]') as HTMLButtonElement;
    button.click();

    expect(sipClientMock.startChatCall).toHaveBeenCalledWith('sip:1002@localhost');
  });

  it('persists a call-started event after the shared call flow starts', async () => {
    sipClientMock.canPlaceCall.mockReturnValue(true);
    component.conversation = {
      id: 11,
      title: 'Room',
      type: 'direct',
      call_target: {
        callable: true,
        user_id: 22,
        display_name: 'Tenant A Sales',
        extension_number: '1002',
        sip_uri: 'sip:1002@localhost',
        target: 'sip:1002@localhost',
      },
    };
    fixture.detectChanges();

    await component.startAudioCall();

    expect(sipClientMock.startChatCall).toHaveBeenCalledWith('sip:1002@localhost');
    expect(chatStateMock.recordCallStarted).toHaveBeenCalledWith({
      target_user_id: 22,
      target_display_name: 'Tenant A Sales',
      target_extension: '1002',
    });
  });

  it('does not persist the call-started event when the target is unavailable', async () => {
    component.conversation = {
      id: 11,
      title: 'Room',
      type: 'direct',
      call_target: {
        callable: false,
        reason: 'No callable target',
      },
    };
    fixture.detectChanges();

    await component.startAudioCall();

    expect(sipClientMock.startChatCall).not.toHaveBeenCalled();
    expect(chatStateMock.recordCallStarted).not.toHaveBeenCalled();
  });

  it('does not create duplicate call-started events while the call start is in flight', async () => {
    sipClientMock.canPlaceCall.mockReturnValue(true);
    let resolveStart!: () => void;
    sipClientMock.startChatCall.mockReturnValue(new Promise<void>((resolve) => {
      resolveStart = resolve;
    }));
    component.conversation = {
      id: 11,
      title: 'Room',
      type: 'direct',
      call_target: {
        callable: true,
        display_name: 'Tenant A Sales',
        extension_number: '1002',
        sip_uri: 'sip:1002@localhost',
        target: 'sip:1002@localhost',
      },
    };
    fixture.detectChanges();

    const firstCall = component.startAudioCall();
    const secondCall = component.startAudioCall();
    resolveStart();
    await firstCall;
    await secondCall;

    expect(sipClientMock.startChatCall).toHaveBeenCalledTimes(1);
    expect(chatStateMock.recordCallStarted).toHaveBeenCalledTimes(1);
  });

  it('keeps the softphone call flow stable when the event API fails', async () => {
    sipClientMock.canPlaceCall.mockReturnValue(true);
    chatStateMock.recordCallStarted.mockRejectedValueOnce(new Error('event write failed'));
    component.conversation = {
      id: 11,
      title: 'Room',
      type: 'direct',
      call_target: {
        callable: true,
        display_name: 'Tenant A Sales',
        extension_number: '1002',
        sip_uri: 'sip:1002@localhost',
        target: 'sip:1002@localhost',
      },
    };
    fixture.detectChanges();

    await expect(component.startAudioCall()).resolves.toBeUndefined();

    expect(sipClientMock.startChatCall).toHaveBeenCalledWith('sip:1002@localhost');
    expect(chatStateMock.recordCallStarted).toHaveBeenCalledTimes(1);
  });

  it('renders a friendly event label for call-started messages', () => {
    component.conversation = { id: 11, title: 'Room' };
    const message = {
      id: 8,
      conversation_id: 11,
      type: 'system',
      body: 'Audio call started',
      metadata: {
        event: 'call_started',
        target_display_name: 'Tenant A Sales',
        target_extension: '1002',
      },
    } as any;

    expect(component.messageDisplayBody(message)).toBe('Audio call started with Tenant A Sales (1002)');
    expect(component.isCallStartedMessage(message)).toBe(true);
  });

  it('renders call-started event messages with a friendly label', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.messages = [
      {
        id: 8,
        conversation_id: 11,
        type: 'system',
        body: 'Audio call started',
        metadata: {
          event: 'call_started',
          target_display_name: 'Tenant A Sales',
          target_extension: '1002',
        },
      } as any,
    ];
    fixture.detectChanges();

    const eventNode = fixture.nativeElement.querySelector('[data-testid="call-started-message"]') as HTMLElement;
    expect(eventNode).not.toBeNull();
    expect(eventNode.textContent).toContain('Audio call started with Tenant A Sales (1002)');
  });

  it('loading state renders safely', () => {
    component.loading = true;
    component.error = null;
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Loading messages...');
  });

  it('error state renders safely', () => {
    component.loading = false;
    component.error = 'Load failed';
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Load failed');
  });

  it('thread renders delivered/sent fallback', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.currentUserId = 9;
    component.messages = [
      { id: 3, conversation_id: 11, sender_id: 9, body: 'A', status: 'delivered' },
      { id: 4, conversation_id: 11, sender_id: 8, body: 'B', status: 'sent' },
    ];
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Delivered');
    expect(fixture.nativeElement.textContent).toContain('Sent');
  });

  it('does not render sensitive device metadata fields', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.currentUserId = 9;
    component.messages = [
      {
        id: 5,
        conversation_id: 11,
        sender_id: 9,
        body: 'safe',
        status: 'sent',
        device_key: 'chatdev_secret',
        user_agent: 'UA',
        ip_address: '127.0.0.1',
      } as any,
    ];
    fixture.detectChanges();

    const content = fixture.nativeElement.textContent as string;
    expect(content).not.toContain('chatdev_secret');
    expect(content).not.toContain('127.0.0.1');
    expect(content).not.toContain('UA');
  });

  it('thread renders attachment list and download link', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.messages = [
      {
        id: 7,
        conversation_id: 11,
        sender_id: 5,
        body: 'With attachment',
        status: 'sent',
        attachments: [
          {
            id: 100,
            message_id: 7,
            conversation_id: 11,
            original_name: 'document.pdf',
            mime_type: 'application/pdf',
            size: 2048,
            status: 'active',
          },
        ],
      },
    ];
    fixture.detectChanges();

    const content = fixture.nativeElement.textContent as string;
    expect(content).toContain('document.pdf');
    expect(content).toContain('application');

    const downloadLink = fixture.nativeElement.querySelector('[data-testid="attachment-download-link"]') as HTMLAnchorElement;
    expect(downloadLink).not.toBeNull();
    expect(downloadLink.href).toContain('/api/v1/chat/attachments/100/download');
  });

  it('hidden state hides thread messages', () => {
    component.conversation = {
      id: 11,
      title: 'Hidden',
      current_user_access: { user_id: 9, access_state: 'hidden' },
    };
    component.messages = [{ id: 1, conversation_id: 11, body: 'Secret' }];
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="message-item"]')).toBeNull();
    expect(fixture.nativeElement.textContent).toContain('This conversation is not available.');
  });

  it('show_read_only_history keeps thread visible', () => {
    component.conversation = {
      id: 11,
      title: 'History',
      current_user_access: { user_id: 9, access_state: 'blocked', block_display_mode: 'show_read_only_history' },
    };
    component.messages = [{ id: 2, conversation_id: 11, body: 'Visible history', status: 'sent' }];
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="message-item"]')).not.toBeNull();
  });

  it('typing indicator renders one user', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.typingUsers = [{ id: 21, name: 'Anatolii' } as any];
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Anatolii is typing...');
  });

  it('typing indicator renders multiple users', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.typingUsers = [{ id: 21, name: 'A' } as any, { id: 22, name: 'B' } as any];
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('A and B are typing...');
  });

  it('typing indicator renders group summary for 3+ users', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.typingUsers = [
      { id: 21, name: 'A' } as any,
      { id: 22, name: 'B' } as any,
      { id: 23, name: 'C' } as any,
    ];
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('A, B and 1 others are typing...');
  });

  it('typing indicator deduplicates users by id', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.typingUsers = [
      { id: 21, name: 'A' } as any,
      { id: 21, name: 'A duplicate' } as any,
      { id: 22, name: 'B' } as any,
    ];
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('A and B are typing...');
  });

  it('current user is not shown as typing', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.currentUserId = 21;
    component.typingUsers = [{ id: 21, name: 'Me' } as any];
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).not.toContain('Me is typing...');
  });

  it('typing indicator does not render sensitive fields', () => {
    component.conversation = { id: 11, title: 'Room' };
    component.typingUsers = [{
      id: 44,
      name: 'Safe',
      email: 'sensitive@example.com',
      ip_address: '127.0.0.1',
      user_agent: 'UA',
      device_key: 'chatdev_secret',
      metadata: { secret: true },
    } as any];
    fixture.detectChanges();

    const content = fixture.nativeElement.textContent as string;
    expect(content).toContain('Safe is typing...');
    expect(content).not.toContain('sensitive@example.com');
    expect(content).not.toContain('127.0.0.1');
    expect(content).not.toContain('UA');
    expect(content).not.toContain('chatdev_secret');
    expect(content).not.toContain('metadata');
  });
});
