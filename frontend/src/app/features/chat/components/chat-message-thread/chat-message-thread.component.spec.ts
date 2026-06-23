import { ComponentFixture, TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { ChatMessageThreadComponent } from './chat-message-thread.component';
import { ChatApiService } from '../../../../core/services/chat-api.service';

describe('ChatMessageThreadComponent', () => {
  let fixture: ComponentFixture<ChatMessageThreadComponent>;
  let component: ChatMessageThreadComponent;

  beforeEach(async () => {
    const chatApiMock = {
      getAttachmentDownloadUrl: vi.fn((id: number) => `http://localhost:8080/api/v1/chat/attachments/${id}/download`),
    };

    await TestBed.configureTestingModule({
      imports: [ChatMessageThreadComponent],
      providers: [{ provide: ChatApiService, useValue: chatApiMock }],
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
