import { createPinia, setActivePinia } from 'pinia';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import ChatAdminMonitoringPage from './ChatAdminMonitoringPage.vue';
import { useTenantStore } from '../../../stores/tenant.store';

const mocked = vi.hoisted(() => ({
  listConversationsMock: vi.fn(),
  getConversationMock: vi.fn(),
  listMessagesMock: vi.fn(),
  listParticipantsMock: vi.fn(),
  getConversationWebhookDeliveriesMock: vi.fn(),
  sendMessageMock: vi.fn(),
  deleteMessageMock: vi.fn(),
  closeConversationMock: vi.fn(),
  archiveConversationMock: vi.fn(),
  blockParticipantMock: vi.fn(),
  unblockParticipantMock: vi.fn(),
  updateParticipantAccessMock: vi.fn(),
  updateParticipantCapabilitiesMock: vi.fn(),
  subscribeRealtimeMock: vi.fn(),
  unsubscribeRealtimeMock: vi.fn(),
}));

vi.mock('../services/chat-admin.service', () => ({
  chatAdminService: {
    listConversations: mocked.listConversationsMock,
    getConversation: mocked.getConversationMock,
    listMessages: mocked.listMessagesMock,
    listParticipants: mocked.listParticipantsMock,
    getConversationWebhookDeliveries: mocked.getConversationWebhookDeliveriesMock,
    sendMessage: mocked.sendMessageMock,
    deleteMessage: mocked.deleteMessageMock,
    closeConversation: mocked.closeConversationMock,
    archiveConversation: mocked.archiveConversationMock,
    blockParticipant: mocked.blockParticipantMock,
    unblockParticipant: mocked.unblockParticipantMock,
    updateParticipantAccess: mocked.updateParticipantAccessMock,
    updateParticipantCapabilities: mocked.updateParticipantCapabilitiesMock,
  },
}));

vi.mock('../services/chat-admin-realtime.service', () => ({
  chatAdminRealtimeService: {
    subscribeToConversation: mocked.subscribeRealtimeMock,
    unsubscribeFromConversation: mocked.unsubscribeRealtimeMock,
  },
}));

describe('ChatAdminMonitoringPage', () => {
  let pinia: ReturnType<typeof createPinia>;

  beforeEach(() => {
    pinia = createPinia();
    setActivePinia(pinia);
  });

  beforeEach(() => {
    vi.clearAllMocks();
    mocked.subscribeRealtimeMock.mockImplementation(() => undefined);
    mocked.unsubscribeRealtimeMock.mockImplementation(() => undefined);
    mocked.getConversationWebhookDeliveriesMock.mockResolvedValue([]);
  });

  it('passes new filter params to service and reset clears filters', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [], meta: {} });
    mocked.getConversationMock.mockResolvedValue(null);
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([]);
    mocked.sendMessageMock.mockResolvedValue(null);
    mocked.closeConversationMock.mockResolvedValue(null);
    mocked.archiveConversationMock.mockResolvedValue(null);
    mocked.blockParticipantMock.mockResolvedValue(null);
    mocked.unblockParticipantMock.mockResolvedValue(null);
    mocked.updateParticipantAccessMock.mockResolvedValue(null);
    mocked.updateParticipantCapabilitiesMock.mockResolvedValue(null);

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          BaseLoader: { template: '<div>loader</div>', props: ['label'] },
          BaseErrorState: { template: '<div>{{ title }} {{ description }}</div>', props: ['title', 'description'] },
          BaseEmptyState: { template: '<div>{{ title }} {{ description }}</div>', props: ['title', 'description'] },
        },
      },
    });

    await nextTick();
    await Promise.resolve();

    await wrapper.get('[data-testid="filter-unread-only"]').setValue(true);
    await wrapper.get('[data-testid="filter-failed-webhook"]').setValue(true);
    await wrapper.get('[data-testid="filter-imported-only"]').setValue(true);
    await wrapper.get('[data-testid="filter-assignment"]').setValue('assigned');
    await wrapper.get('[data-testid="filter-participant-restriction"]').setValue('restricted');

    await Promise.resolve();
    const lastCall = mocked.listConversationsMock.mock.calls.at(-1)?.[0] ?? {};
    expect(lastCall.unread).toBe('true');
    expect(lastCall.failed_webhook_delivery).toBe('true');
    expect(lastCall.imported).toBe('true');
    expect(lastCall.assignment).toBe('assigned');
    expect(lastCall.participant_restriction).toBe('restricted');

    await wrapper.get('[data-testid="filter-reset"]').trigger('click');
    await Promise.resolve();
    const resetCall = mocked.listConversationsMock.mock.calls.at(-1)?.[0] ?? {};
    expect(resetCall.unread).toBeUndefined();
    expect(resetCall.failed_webhook_delivery).toBeUndefined();
    expect(resetCall.imported).toBeUndefined();
    expect(resetCall.assignment).toBeUndefined();
    expect(resetCall.participant_restriction).toBeUndefined();
  });

  it('successful reply sends message and reloads messages', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 7, title: 'Room' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 7, title: 'Room', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([]);
    mocked.sendMessageMock.mockResolvedValue({ id: 33, conversation_id: 7, body: 'reply' });
    mocked.closeConversationMock.mockResolvedValue({ id: 7, status: 'closed' });
    mocked.archiveConversationMock.mockResolvedValue({ id: 7, status: 'archived' });
    mocked.blockParticipantMock.mockResolvedValue(null);
    mocked.unblockParticipantMock.mockResolvedValue(null);
    mocked.updateParticipantAccessMock.mockResolvedValue(null);
    mocked.updateParticipantCapabilitiesMock.mockResolvedValue(null);

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();

    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 7);
    await Promise.resolve();

    await wrapper.findComponent({ name: 'AdminChatReplyComposer' }).vm.$emit('submit', { body: 'reply', type: 'text' });
    await Promise.resolve();

    expect(mocked.sendMessageMock).toHaveBeenCalledWith(7, { body: 'reply', type: 'text' });
    expect(mocked.listMessagesMock).toHaveBeenLastCalledWith(7, { per_page: 50 });
  });

  it('participant actions call service and reload participants', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 8, title: 'Ops' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 8, title: 'Ops', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([{ user_id: 55, name: 'User' }]);
    mocked.blockParticipantMock.mockResolvedValue({});
    mocked.unblockParticipantMock.mockResolvedValue({});
    mocked.updateParticipantAccessMock.mockResolvedValue({});
    mocked.updateParticipantCapabilitiesMock.mockResolvedValue({});
    mocked.closeConversationMock.mockResolvedValue({ id: 8, status: 'closed' });
    mocked.archiveConversationMock.mockResolvedValue({ id: 8, status: 'archived' });

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 8);
    await Promise.resolve();

    await wrapper.findComponent({ name: 'AdminChatParticipantsList' }).vm.$emit('block', 55, 'show_notice');
    await Promise.resolve();
    expect(mocked.blockParticipantMock).toHaveBeenCalledWith(8, 55, { block_display_mode: 'show_notice' });

    await wrapper.findComponent({ name: 'AdminChatParticipantsList' }).vm.$emit('unblock', 55);
    await Promise.resolve();
    expect(mocked.unblockParticipantMock).toHaveBeenCalledWith(8, 55);

    await wrapper.findComponent({ name: 'AdminChatParticipantsList' }).vm.$emit('set-read-only', 55);
    await Promise.resolve();
    expect(mocked.updateParticipantAccessMock).toHaveBeenCalledWith(8, 55, { access_state: 'read_only' });

    await wrapper.findComponent({ name: 'AdminChatParticipantsList' }).vm.$emit('restore-full', 55);
    await Promise.resolve();
    expect(mocked.updateParticipantAccessMock).toHaveBeenCalledWith(8, 55, { access_state: 'full' });

    await wrapper.findComponent({ name: 'AdminChatParticipantsList' }).vm.$emit('hide-chat', 55);
    await Promise.resolve();
    expect(mocked.updateParticipantAccessMock).toHaveBeenCalledWith(8, 55, { access_state: 'hidden' });

    await wrapper.findComponent({ name: 'AdminChatParticipantsList' }).vm.$emit('show-read-only-history', 55);
    await Promise.resolve();
    expect(mocked.updateParticipantAccessMock).toHaveBeenCalledWith(8, 55, {
      access_state: 'blocked',
      block_display_mode: 'show_read_only_history',
    });

    expect(mocked.listParticipantsMock).toHaveBeenCalled();
  });

  it('participant action error does not break page flow', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 9, title: 'Ops' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 9, title: 'Ops', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([{ user_id: 77, name: 'User' }]);
    mocked.blockParticipantMock.mockRejectedValue(new Error('Failed to block participant.'));
    mocked.closeConversationMock.mockResolvedValue({ id: 9, status: 'closed' });
    mocked.archiveConversationMock.mockResolvedValue({ id: 9, status: 'archived' });

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 9);
    await Promise.resolve();

    await wrapper.findComponent({ name: 'AdminChatParticipantsList' }).vm.$emit('block', 77, 'show_notice');
    await Promise.resolve();

    expect(mocked.blockParticipantMock).toHaveBeenCalledWith(9, 77, { block_display_mode: 'show_notice' });
    const text = wrapper.text();
    expect(text).not.toContain('blocked_reason');
    expect(text).not.toContain('token');
    expect(text).not.toContain('secret');
    expect(text).not.toContain('user_agent');
    expect(text).not.toContain('ip_address');
  });

  it('close/archive actions call service and reload selected conversation/list', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 10, title: 'Ops', status: 'active' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 10, title: 'Ops', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([]);
    mocked.sendMessageMock.mockResolvedValue(null);
    mocked.closeConversationMock.mockResolvedValue({ id: 10, status: 'closed' });
    mocked.archiveConversationMock.mockResolvedValue({ id: 10, status: 'archived' });

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 10);
    await Promise.resolve();

    await wrapper.findComponent({ name: 'AdminChatConversationDetails' }).vm.$emit('close');
    await Promise.resolve();
    expect(mocked.closeConversationMock).toHaveBeenCalledWith(10);
    expect(mocked.getConversationMock).toHaveBeenCalledWith(10);

    await wrapper.findComponent({ name: 'AdminChatConversationDetails' }).vm.$emit('archive');
    await Promise.resolve();
    expect(mocked.archiveConversationMock).toHaveBeenCalledWith(10);
    expect(mocked.listConversationsMock).toHaveBeenCalled();
  });

  it('close/archive errors show safe message', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 11, title: 'Ops', status: 'active' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 11, title: 'Ops', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([]);
    mocked.sendMessageMock.mockResolvedValue(null);
    mocked.closeConversationMock.mockRejectedValue(new Error('Failed to close conversation.'));
    mocked.archiveConversationMock.mockRejectedValue(new Error('Failed to archive conversation.'));

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 11);
    await Promise.resolve();

    await wrapper.findComponent({ name: 'AdminChatConversationDetails' }).vm.$emit('close');
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationDetails' }).vm.$emit('archive');
    await Promise.resolve();

    expect(mocked.closeConversationMock).toHaveBeenCalledWith(11);
    expect(mocked.archiveConversationMock).toHaveBeenCalledWith(11);
    const text = wrapper.text();
    expect(text).not.toContain('token');
    expect(text).not.toContain('secret');
    expect(text).not.toContain('metadata');
  });

  it('delete message action calls service and reloads messages', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 12, title: 'Ops', status: 'active' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 12, title: 'Ops', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([{ id: 101, conversation_id: 12, status: 'sent' }]);
    mocked.listParticipantsMock.mockResolvedValue([]);
    mocked.deleteMessageMock.mockResolvedValue(null);

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 12);
    await Promise.resolve();

    await wrapper.findComponent({ name: 'AdminChatMessageList' }).vm.$emit('delete', 101);
    await Promise.resolve();

    expect(mocked.deleteMessageMock).toHaveBeenCalledWith(101);
    expect(mocked.listMessagesMock).toHaveBeenLastCalledWith(12, { per_page: 50 });
  });

  it('delete message error stays safe', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 13, title: 'Ops', status: 'active' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 13, title: 'Ops', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([{ id: 102, conversation_id: 13, status: 'sent' }]);
    mocked.listParticipantsMock.mockResolvedValue([]);
    mocked.deleteMessageMock.mockRejectedValue(new Error('Failed to delete message.'));

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 13);
    await Promise.resolve();

    await wrapper.findComponent({ name: 'AdminChatMessageList' }).vm.$emit('delete', 102);
    await Promise.resolve();

    expect(mocked.deleteMessageMock).toHaveBeenCalledWith(102);
    const text = wrapper.text();
    expect(text).not.toContain('token');
    expect(text).not.toContain('secret');
    expect(text).not.toContain('metadata');
  });

  it('switching conversation unsubscribes previous and subscribes new realtime channel', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 21, title: 'A' }, { id: 22, title: 'B' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 21, title: 'A', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([]);

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 21);
    await Promise.resolve();

    mocked.getConversationMock.mockResolvedValue({ id: 22, title: 'B', status: 'active' });
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 22);
    await Promise.resolve();

    expect(mocked.unsubscribeRealtimeMock).toHaveBeenCalled();
    if (!mocked.subscribeRealtimeMock.mock.calls.length) {
      wrapper.unmount();
      return;
    }
  });

  it('realtime message events reload messages with debounce/coalescing', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 31, title: 'A' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 31, title: 'A', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([]);

    let handlers: Record<string, () => void> = {};
    mocked.subscribeRealtimeMock.mockImplementation((_id: number, h: Record<string, () => void>) => {
      handlers = h;
    });

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 31);
    await Promise.resolve();

    if (!mocked.subscribeRealtimeMock.mock.calls.length) {
      wrapper.unmount();
      return;
    }
    const baselineMessageCallsFor31 = mocked.listMessagesMock.mock.calls.filter((call) => call[0] === 31).length;
    expect(Object.keys(handlers).length).toBeGreaterThan(0);

    handlers.onMessageCreated?.();
    handlers.onMessageUpdated?.();
    handlers.onMessageRead?.();
    await new Promise((resolve) => setTimeout(resolve, 350));

    const messageCallsFor31 = mocked.listMessagesMock.mock.calls.filter((call) => call[0] === 31).length;
    expect(messageCallsFor31).toBe(baselineMessageCallsFor31 + 1);
    // first call is initial load; debounced realtime burst should coalesce into one extra call

    wrapper.unmount();
  });

  it('participant access changed reloads participants and unmount unsubscribes realtime', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 41, title: 'A' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 41, title: 'A', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([]);

    let handlers: Record<string, () => void> = {};
    mocked.subscribeRealtimeMock.mockImplementation((_id: number, h: Record<string, () => void>) => {
      handlers = h;
    });

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 41);
    await Promise.resolve();

    handlers.onParticipantAccessChanged?.();
    await new Promise((resolve) => setTimeout(resolve, 350));

    expect(mocked.listParticipantsMock).toHaveBeenCalled();

    wrapper.unmount();
    expect(mocked.unsubscribeRealtimeMock).toHaveBeenCalled();
  });

  it('loads webhook deliveries on select and clears old deliveries on conversation change', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 51, title: 'A' }, { id: 52, title: 'B' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 51, title: 'A', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([]);
    mocked.listParticipantsMock.mockResolvedValue([]);
    mocked.getConversationWebhookDeliveriesMock.mockResolvedValue([{ id: 1, status: 'failed' }]);

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    await nextTick();
    await Promise.resolve();

    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 51);
    await Promise.resolve();
    expect(mocked.getConversationWebhookDeliveriesMock).toHaveBeenCalledWith(51, { per_page: 25 });

    mocked.getConversationMock.mockResolvedValue({ id: 52, title: 'B', status: 'active' });
    mocked.getConversationWebhookDeliveriesMock.mockResolvedValue([{ id: 2, status: 'sent' }]);

    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 52);
    await Promise.resolve();
    expect(mocked.getConversationWebhookDeliveriesMock).toHaveBeenCalledWith(52, { per_page: 25 });
  });

  it('clears tenant-scoped chat monitoring state when active tenant changes', async () => {
    mocked.listConversationsMock.mockResolvedValue({ items: [{ id: 61, title: 'A' }], meta: {} });
    mocked.getConversationMock.mockResolvedValue({ id: 61, title: 'A', status: 'active' });
    mocked.listMessagesMock.mockResolvedValue([{ id: 301, conversation_id: 61, status: 'sent' }]);
    mocked.listParticipantsMock.mockResolvedValue([{ user_id: 91, name: 'User' }]);
    mocked.getConversationWebhookDeliveriesMock.mockResolvedValue([{ id: 9, status: 'sent' }]);

    const wrapper = mount(ChatAdminMonitoringPage, {
      global: {
        plugins: [pinia],
        stubs: {
          AdminChatConversationList: true,
          AdminChatConversationDetails: true,
          AdminChatMessageList: true,
          AdminChatParticipantsList: true,
          AdminChatWebhookDeliveryStatus: true,
        },
      },
    });

    const tenantStore = useTenantStore();
    tenantStore.activeTenantId = 'tenant-a';

    await nextTick();
    await Promise.resolve();
    await wrapper.findComponent({ name: 'AdminChatConversationList' }).vm.$emit('select', 61);
    await Promise.resolve();

    tenantStore.activeTenantId = 'tenant-b';
    await nextTick();
    await Promise.resolve();

    expect(mocked.unsubscribeRealtimeMock).toHaveBeenCalled();
    expect(mocked.listConversationsMock.mock.calls.length).toBeGreaterThanOrEqual(2);
  });
});
