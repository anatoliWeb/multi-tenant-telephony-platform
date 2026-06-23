import { Subject } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { ChatRealtimeClientService } from './chat-realtime-client.service';

describe('ChatRealtimeClientService', () => {
  it('subscribe listens created/updated/deleted events', () => {
    const created$ = new Subject<any>();
    const updated$ = new Subject<any>();
    const deleted$ = new Subject<any>();
    const read$ = new Subject<any>();
    const deviceRead$ = new Subject<any>();
    const deliveryUpdated$ = new Subject<any>();

    const realtimeMock = {
      connect: vi.fn(),
      joinChatMessages: vi.fn(),
      leaveChatMessages: vi.fn(),
      observeChatMessageCreated: vi.fn().mockReturnValue(created$.asObservable()),
      observeChatMessageUpdated: vi.fn().mockReturnValue(updated$.asObservable()),
      observeChatMessageDeleted: vi.fn().mockReturnValue(deleted$.asObservable()),
      observeChatMessageRead: vi.fn().mockReturnValue(read$.asObservable()),
      observeChatMessageDeviceRead: vi.fn().mockReturnValue(deviceRead$.asObservable()),
      observeChatMessageDeliveryUpdated: vi.fn().mockReturnValue(deliveryUpdated$.asObservable()),
    };

    const service = new ChatRealtimeClientService(realtimeMock as any);
    const handlers = {
      onMessageCreated: vi.fn(),
      onMessageUpdated: vi.fn(),
      onMessageDeleted: vi.fn(),
      onMessageRead: vi.fn(),
      onMessageDeviceRead: vi.fn(),
      onMessageDeliveryUpdated: vi.fn(),
    };
    service.subscribeToConversation(5, handlers);

    created$.next({ id: 1 });
    updated$.next({ id: 1, body: 'x' });
    deleted$.next({ id: 1, status: 'deleted' });
    read$.next({ message_id: 1, read_at: '2026-01-01T00:00:00Z' });
    deviceRead$.next({ message_id: 1, read_at: '2026-01-01T00:00:00Z' });
    deliveryUpdated$.next({ message_id: 1, delivery_status: 'delivered' });

    expect(realtimeMock.joinChatMessages).toHaveBeenCalledWith(5);
    expect(handlers.onMessageCreated).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    expect(handlers.onMessageUpdated).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    expect(handlers.onMessageDeleted).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    expect(handlers.onMessageRead).toHaveBeenCalledWith(expect.objectContaining({ message_id: 1 }));
    expect(handlers.onMessageDeviceRead).toHaveBeenCalledWith(expect.objectContaining({ message_id: 1 }));
    expect(handlers.onMessageDeliveryUpdated).toHaveBeenCalledWith(expect.objectContaining({ message_id: 1 }));
  });

  it('unsubscribeFromConversation leaves channel', () => {
    const created$ = new Subject<any>();
    const updated$ = new Subject<any>();
    const deleted$ = new Subject<any>();
    const read$ = new Subject<any>();
    const deviceRead$ = new Subject<any>();
    const deliveryUpdated$ = new Subject<any>();
    const realtimeMock = {
      connect: vi.fn(),
      joinChatMessages: vi.fn(),
      leaveChatMessages: vi.fn(),
      observeChatMessageCreated: vi.fn().mockReturnValue(created$.asObservable()),
      observeChatMessageUpdated: vi.fn().mockReturnValue(updated$.asObservable()),
      observeChatMessageDeleted: vi.fn().mockReturnValue(deleted$.asObservable()),
      observeChatMessageRead: vi.fn().mockReturnValue(read$.asObservable()),
      observeChatMessageDeviceRead: vi.fn().mockReturnValue(deviceRead$.asObservable()),
      observeChatMessageDeliveryUpdated: vi.fn().mockReturnValue(deliveryUpdated$.asObservable()),
    };
    const service = new ChatRealtimeClientService(realtimeMock as any);
    service.subscribeToConversation(9, {
      onMessageCreated: vi.fn(),
      onMessageUpdated: vi.fn(),
      onMessageDeleted: vi.fn(),
      onMessageRead: vi.fn(),
      onMessageDeviceRead: vi.fn(),
      onMessageDeliveryUpdated: vi.fn(),
    });
    service.unsubscribeFromConversation();
    expect(realtimeMock.leaveChatMessages).toHaveBeenCalledWith(9);
  });
});
