import { BehaviorSubject } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { ChatPresenceClientService } from './chat-presence-client.service';
import { RealtimeService } from '../../../realtime/services/realtime.service';

describe('ChatPresenceClientService', () => {
  it('joins presence and handles here/joining/leaving', () => {
    const stream = new BehaviorSubject<any[]>([]);
    const realtimeMock = {
      connect: vi.fn(),
      joinPresence: vi.fn(),
      leavePresence: vi.fn(),
      observePresence: vi.fn().mockReturnValue(stream.asObservable()),
    } as unknown as RealtimeService;

    const service = new ChatPresenceClientService(realtimeMock);
    const onHere = vi.fn();
    const onJoining = vi.fn();
    const onLeaving = vi.fn();

    service.joinConversationPresence(15, { onHere, onJoining, onLeaving });
    stream.next([{ id: 1, name: 'Alice' }]);
    stream.next([{ id: 1, name: 'Alice' }, { id: 2, name: 'Bob' }]);
    stream.next([{ id: 2, name: 'Bob' }]);

    expect((realtimeMock as any).joinPresence).toHaveBeenCalledWith('presence-chat.15');
    expect(onHere).toHaveBeenCalled();
    expect(onJoining).toHaveBeenCalledWith(expect.objectContaining({ id: 2 }));
    expect(onLeaving).toHaveBeenCalledWith(1);
  });

  it('leaveConversationPresence cleans up channel', () => {
    const stream = new BehaviorSubject<any[]>([]);
    const realtimeMock = {
      connect: vi.fn(),
      joinPresence: vi.fn(),
      leavePresence: vi.fn(),
      observePresence: vi.fn().mockReturnValue(stream.asObservable()),
    } as unknown as RealtimeService;

    const service = new ChatPresenceClientService(realtimeMock);
    service.joinConversationPresence(21, {
      onHere: vi.fn(),
      onJoining: vi.fn(),
      onLeaving: vi.fn(),
    });
    service.leaveConversationPresence();

    expect((realtimeMock as any).leavePresence).toHaveBeenCalledWith('presence-chat.21');
  });
});

