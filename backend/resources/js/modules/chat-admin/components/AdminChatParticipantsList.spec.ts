import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AdminChatParticipantsList from './AdminChatParticipantsList.vue';

const globalStubs = {
  BaseLoader: { template: '<div data-testid="loader">loader</div>', props: ['label'] },
  BaseErrorState: { template: '<div data-testid="error">{{ title }} {{ description }}</div>', props: ['title', 'description'] },
  BaseEmptyState: { template: '<div data-testid="empty">{{ title }} {{ description }}</div>', props: ['title', 'description'] },
};

describe('AdminChatParticipantsList', () => {
  it('renders participant safe fields', () => {
    const wrapper = mount(AdminChatParticipantsList, {
      props: {
        loading: false,
        error: '',
        items: [
          {
            user_id: 42,
            name: 'Alice',
            role: 'admin',
            status: 'active',
            access_state: 'full',
          },
        ],
      },
      global: { stubs: globalStubs },
    });

    const text = wrapper.text();
    expect(text).toContain('Alice');
    expect(text).toContain('#42');
    expect(text).toContain('admin');
    expect(text).toContain('active');
    expect(text).toContain('full');
  });

  it('renders loading/empty states and hides sensitive fields', async () => {
    const wrapper = mount(AdminChatParticipantsList, {
      props: {
        loading: true,
        error: '',
        items: [],
      },
      global: { stubs: globalStubs },
    });

    expect(wrapper.find('[data-testid="loader"]').exists()).toBe(true);

    await wrapper.setProps({
      loading: false,
      items: [
        {
          user_id: 50,
          name: 'Bob',
          role: 'member',
          status: 'blocked',
          access_state: 'hidden',
          blocked_reason: 'sensitive',
          metadata: { internal_permission: 'chat.admin.moderate' },
        },
      ],
    });

    const text = wrapper.text();
    expect(text).toContain('Bob');
    expect(text).not.toContain('blocked_reason');
    expect(text).not.toContain('sensitive');
    expect(text).not.toContain('internal_permission');
    expect(text).not.toContain('chat.admin.moderate');

    await wrapper.setProps({
      items: [],
    });
    expect(wrapper.find('[data-testid="empty"]').exists()).toBe(true);
  });

  it('emits participant access actions and disables buttons when loading', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const wrapper = mount(AdminChatParticipantsList, {
      props: {
        loading: false,
        error: '',
        items: [
          {
            user_id: 77,
            name: 'Action User',
            role: 'member',
            status: 'active',
            access_state: 'full',
          },
        ],
        actionLoadingUserIds: [],
        actionError: '',
      },
      global: { stubs: globalStubs },
    });

    const buttons = wrapper.findAll('button.chat-admin-participants__action-btn');
    await buttons[0].trigger('click'); // block (confirm)
    await buttons[1].trigger('click'); // unblock
    await buttons[2].trigger('click'); // readonly
    await buttons[3].trigger('click'); // full
    await buttons[4].trigger('click'); // hide (confirm)
    await buttons[5].trigger('click'); // show history

    expect(wrapper.emitted('block')?.[0]).toEqual([77, 'show_notice']);
    expect(wrapper.emitted('unblock')?.[0]).toEqual([77]);
    expect(wrapper.emitted('set-read-only')?.[0]).toEqual([77]);
    expect(wrapper.emitted('restore-full')?.[0]).toEqual([77]);
    expect(wrapper.emitted('hide-chat')?.[0]).toEqual([77]);
    expect(wrapper.emitted('show-read-only-history')?.[0]).toEqual([77]);

    await wrapper.setProps({ actionLoadingUserIds: [77] });
    wrapper.findAll('button.chat-admin-participants__action-btn').forEach((button) => {
      expect((button.element as HTMLButtonElement).disabled).toBe(true);
    });

    confirmSpy.mockRestore();
  });

  it('renders action error safely', () => {
    const wrapper = mount(AdminChatParticipantsList, {
      props: {
        loading: false,
        error: '',
        items: [
          {
            user_id: 90,
            name: 'Err User',
            role: 'member',
            status: 'active',
            access_state: 'full',
          },
        ],
        actionLoadingUserIds: [],
        actionError: 'Failed to block participant.',
      },
      global: { stubs: globalStubs },
    });

    const text = wrapper.text();
    expect(text).toContain('Failed to block participant.');
    expect(text).not.toContain('token');
    expect(text).not.toContain('secret');
    expect(text).not.toContain('blocked_reason');
  });
});
