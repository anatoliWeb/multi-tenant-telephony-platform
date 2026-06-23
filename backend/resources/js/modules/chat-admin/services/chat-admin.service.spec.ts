import { describe, expect, it, vi } from 'vitest';
import { chatAdminService } from './chat-admin.service';

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
  put: vi.fn(),
  delete: vi.fn(),
}));

vi.mock('../../../services/api/client', () => ({
  api: apiMock,
}));

describe('chatAdminService participant actions', () => {
  it('block action calls API with block_display_mode payload', async () => {
    apiMock.patch.mockResolvedValue({ data: { user_id: 11 } });
    await chatAdminService.blockParticipant(7, 11, { block_display_mode: 'show_notice' });
    expect(apiMock.patch).toHaveBeenCalledWith(
      '/v1/chat/conversations/7/participants/11/block',
      { block_display_mode: 'show_notice' },
    );
  });

  it('unblock action calls API', async () => {
    apiMock.patch.mockResolvedValue({ data: { user_id: 11 } });
    await chatAdminService.unblockParticipant(7, 11);
    expect(apiMock.patch).toHaveBeenCalledWith('/v1/chat/conversations/7/participants/11/unblock', {});
  });

  it('set read-only/full/hide/history call update access API payloads', async () => {
    apiMock.patch.mockResolvedValue({ data: { user_id: 11 } });

    await chatAdminService.updateParticipantAccess(7, 11, { access_state: 'read_only' });
    expect(apiMock.patch).toHaveBeenCalledWith(
      '/v1/chat/conversations/7/participants/11/access',
      { access_state: 'read_only' },
    );

    await chatAdminService.updateParticipantAccess(7, 11, { access_state: 'full' });
    expect(apiMock.patch).toHaveBeenCalledWith(
      '/v1/chat/conversations/7/participants/11/access',
      { access_state: 'full' },
    );

    await chatAdminService.updateParticipantAccess(7, 11, { access_state: 'hidden' });
    expect(apiMock.patch).toHaveBeenCalledWith(
      '/v1/chat/conversations/7/participants/11/access',
      { access_state: 'hidden' },
    );

    await chatAdminService.updateParticipantAccess(7, 11, {
      access_state: 'blocked',
      block_display_mode: 'show_read_only_history',
    });
    expect(apiMock.patch).toHaveBeenCalledWith(
      '/v1/chat/conversations/7/participants/11/access',
      { access_state: 'blocked', block_display_mode: 'show_read_only_history' },
    );
  });
});

describe('chatAdminService conversation lifecycle actions', () => {
  it('closeConversation calls close endpoint', async () => {
    apiMock.patch.mockResolvedValue({ data: { id: 7, status: 'closed' } });
    await chatAdminService.closeConversation(7);
    expect(apiMock.patch).toHaveBeenCalledWith('/v1/chat/conversations/7/close', {});
  });

  it('archiveConversation calls archive endpoint', async () => {
    apiMock.patch.mockResolvedValue({ data: { id: 7, status: 'archived' } });
    await chatAdminService.archiveConversation(7);
    expect(apiMock.patch).toHaveBeenCalledWith('/v1/chat/conversations/7/archive', {});
  });

  it('deleteMessage calls delete endpoint', async () => {
    apiMock.delete.mockResolvedValue({});
    await chatAdminService.deleteMessage(99);
    expect(apiMock.delete).toHaveBeenCalledWith('/v1/chat/messages/99');
  });

  it('getConversationWebhookDeliveries calls webhook deliveries endpoint', async () => {
    apiMock.get.mockResolvedValue({ data: [{ id: 1, status: 'failed' }] });
    await chatAdminService.getConversationWebhookDeliveries(7, { per_page: 25 });
    expect(apiMock.get).toHaveBeenCalledWith('/v1/chat/conversations/7/webhook-deliveries', {
      params: { per_page: 25 },
    });
  });

  it('getUnreadConversationsCount uses unread filter and pagination metadata total', async () => {
    apiMock.get.mockResolvedValue({
      data: [],
      meta: { total: 8, current_page: 1, per_page: 1, last_page: 8 },
    });

    const count = await chatAdminService.getUnreadConversationsCount();

    expect(apiMock.get).toHaveBeenCalledWith('/v1/chat/conversations', {
      params: { unread: true, per_page: 1 },
    });
    expect(count).toBe(8);
  });
});
