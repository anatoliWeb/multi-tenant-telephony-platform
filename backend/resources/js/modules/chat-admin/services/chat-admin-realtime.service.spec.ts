import { describe, expect, it, vi } from 'vitest';

const mockedRealtime = vi.hoisted(() => ({
  subscribeToPrivateChannel: vi.fn(),
}));

vi.mock('../../../shared/services/realtime/realtime.client', () => ({
  realtimeClient: mockedRealtime,
}));

describe('chatAdminRealtimeService', async () => {
  const { chatAdminRealtimeService } = await import('./chat-admin-realtime.service');

  it('subscribe uses private channel chat.conversation.{id}', () => {
    mockedRealtime.subscribeToPrivateChannel.mockReturnValue(() => undefined);
    chatAdminRealtimeService.subscribeToConversation(77, {});
    expect(mockedRealtime.subscribeToPrivateChannel).toHaveBeenCalledWith(
      'chat.conversation.77',
      expect.any(Object),
    );
  });

  it('unsubscribe leaves previous channel via unsubscribe callback', () => {
    const unsub = vi.fn();
    mockedRealtime.subscribeToPrivateChannel.mockReturnValue(unsub);

    chatAdminRealtimeService.subscribeToConversation(1, {});
    chatAdminRealtimeService.subscribeToConversation(2, {});
    expect(unsub).toHaveBeenCalledTimes(1);

    chatAdminRealtimeService.unsubscribeFromConversation();
  });
});
