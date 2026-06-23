import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AdminChatConversationList from './AdminChatConversationList.vue';

const globalStubs = {
  BaseLoader: { template: '<div data-testid="loader">loader</div>', props: ['label'] },
  BaseErrorState: { template: '<div data-testid="error">{{ title }} {{ description }}</div>', props: ['title', 'description'] },
  BaseEmptyState: { template: '<div data-testid="empty">{{ title }} {{ description }}</div>', props: ['title', 'description'] },
};

describe('AdminChatConversationList', () => {
  it('renders loading and empty states', async () => {
    const wrapper = mount(AdminChatConversationList, {
      props: {
        loading: true,
        error: '',
        selectedConversationId: null,
        items: [],
      },
      global: { stubs: globalStubs },
    });

    expect(wrapper.find('[data-testid="loader"]').exists()).toBe(true);

    await wrapper.setProps({ loading: false, items: [] });
    expect(wrapper.find('[data-testid="empty"]').exists()).toBe(true);
  });

  it('renders safe badges and does not render sensitive fields', () => {
    const wrapper = mount(AdminChatConversationList, {
      props: {
        loading: false,
        error: '',
        selectedConversationId: 1,
        items: [
          {
            id: 1,
            title: 'Ops Chat',
            type: 'group',
            visibility: 'private',
            status: 'active',
            source: 'api',
            unread_count: 4,
            assigned_admin_id: 10,
            restricted_participants_count: 2,
            failed_webhook_deliveries_count: 1,
            imported_messages_count: 7,
            blocked_reason: 'secret',
            metadata: { internal_permission: 'chat.admin.moderate' },
          } as any,
        ],
      },
      global: { stubs: globalStubs },
    });

    const text = wrapper.text();
    expect(text).toContain('Unread: 4');
    expect(text).toContain('Assigned');
    expect(text).toContain('Restricted: 2');
    expect(text).toContain('Webhook failed: 1');
    expect(text).toContain('Imported: 7');

    expect(text).not.toContain('blocked_reason');
    expect(text).not.toContain('secret');
    expect(text).not.toContain('internal_permission');
    expect(text).not.toContain('chat.admin.moderate');
  });
});

