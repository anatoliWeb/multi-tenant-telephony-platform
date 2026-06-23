import { BehaviorSubject, of } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { ChatTypingClientService } from './chat-typing-client.service';

describe('ChatTypingClientService', () => {
  it('subscribe listens typing started/stopped', () => {
    const stream = new BehaviorSubject<any[]>([]);
    const realtimeMock = {
      connect: vi.fn(),
      joinChatTyping: vi.fn(),
      leaveChatTyping: vi.fn(),
      observeChatTyping: vi.fn().mockReturnValue(stream.asObservable()),
    };
    const chatApiMock = { startTyping: vi.fn(), stopTyping: vi.fn() };
    const chatDeviceMock = { ensureRegistered: vi.fn(), getDeviceKey: vi.fn() };

    const service = new ChatTypingClientService(realtimeMock as any, chatApiMock as any, chatDeviceMock as any);
    const onStarted = vi.fn();
    const onStopped = vi.fn();

    service.subscribeToTyping(7, { onStarted, onStopped });
    stream.next([{ conversation_id: 7, user_id: 1, name: 'A' }]);
    stream.next([{ conversation_id: 7, user_id: 1, name: 'A' }, { conversation_id: 7, user_id: 2, name: 'B' }]);
    stream.next([{ conversation_id: 7, user_id: 2, name: 'B' }]);

    expect(realtimeMock.joinChatTyping).toHaveBeenCalledWith(7);
    expect(onStarted).toHaveBeenCalledWith(expect.objectContaining({ user_id: 1 }));
    expect(onStarted).toHaveBeenCalledWith(expect.objectContaining({ user_id: 2 }));
    expect(onStopped).toHaveBeenCalledWith(expect.objectContaining({ user_id: 1 }));
  });

  it('emitTypingStarted/Stopped call API with device key', async () => {
    const stream = new BehaviorSubject<any[]>([]);
    const realtimeMock = {
      connect: vi.fn(),
      joinChatTyping: vi.fn(),
      leaveChatTyping: vi.fn(),
      observeChatTyping: vi.fn().mockReturnValue(stream.asObservable()),
    };
    const chatApiMock = {
      startTyping: vi.fn().mockReturnValue(of({})),
      stopTyping: vi.fn().mockReturnValue(of({})),
    };
    const chatDeviceMock = {
      ensureRegistered: vi.fn().mockResolvedValue(undefined),
      getDeviceKey: vi.fn().mockReturnValue('chatdev_test'),
    };

    const service = new ChatTypingClientService(realtimeMock as any, chatApiMock as any, chatDeviceMock as any);
    await service.emitTypingStarted(9);
    await service.emitTypingStopped(9);

    expect(chatApiMock.startTyping).toHaveBeenCalledWith(9, { device_key: 'chatdev_test', device_type: 'browser' });
    expect(chatApiMock.stopTyping).toHaveBeenCalledWith(9, { device_key: 'chatdev_test', device_type: 'browser' });
  });
});

