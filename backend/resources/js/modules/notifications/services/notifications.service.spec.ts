import { beforeEach, describe, expect, it, vi } from 'vitest';

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}));

vi.mock('../../../services/api/client', () => ({
  api: apiMock,
}));

vi.mock('../../../shared/services/realtime/realtime.client', () => ({
  realtimeClient: {
    onSystemNotification: vi.fn(() => () => undefined),
    onNotificationCreated: vi.fn(() => () => undefined),
  },
}));

describe('notificationsService API usage', async () => {
  const { notificationsService } = await import('./notifications.service');

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('loads unread count via shared api client endpoint', async () => {
    apiMock.get.mockResolvedValue({ data: { count: 7 } });

    await notificationsService.loadUnreadCount();

    expect(apiMock.get).toHaveBeenCalledWith('/v1/notifications/unread-count');
    expect(notificationsService.unreadCount.value).toBe(7);
  });
});

