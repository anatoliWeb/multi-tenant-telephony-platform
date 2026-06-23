import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AdminChatConversationDetails from './AdminChatConversationDetails.vue';

describe('AdminChatConversationDetails', () => {
  const conversation = {
    id: 7,
    title: 'Ops',
    status: 'active',
    type: 'group',
    visibility: 'private',
  };

  it('renders close/archive buttons for active conversation', () => {
    const wrapper = mount(AdminChatConversationDetails, {
      props: { conversation },
    });

    expect(wrapper.get('[data-testid="conversation-close-button"]').exists()).toBe(true);
    expect(wrapper.get('[data-testid="conversation-archive-button"]').exists()).toBe(true);
  });

  it('close action confirms and emits event', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const wrapper = mount(AdminChatConversationDetails, {
      props: { conversation },
    });

    await wrapper.get('[data-testid="conversation-close-button"]').trigger('click');
    expect(confirmSpy).toHaveBeenCalled();
    expect(wrapper.emitted('close')).toBeTruthy();
    confirmSpy.mockRestore();
  });

  it('archive action confirms and emits event', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const wrapper = mount(AdminChatConversationDetails, {
      props: { conversation },
    });

    await wrapper.get('[data-testid="conversation-archive-button"]').trigger('click');
    expect(confirmSpy).toHaveBeenCalled();
    expect(wrapper.emitted('archive')).toBeTruthy();
    confirmSpy.mockRestore();
  });

  it('disables buttons for closed/archived/deleted states and loading', async () => {
    const closedWrapper = mount(AdminChatConversationDetails, {
      props: { conversation: { ...conversation, status: 'closed' } },
    });
    expect((closedWrapper.get('[data-testid="conversation-close-button"]').element as HTMLButtonElement).disabled).toBe(true);

    const archivedWrapper = mount(AdminChatConversationDetails, {
      props: { conversation: { ...conversation, status: 'archived' } },
    });
    expect((archivedWrapper.get('[data-testid="conversation-archive-button"]').element as HTMLButtonElement).disabled).toBe(true);

    const deletedWrapper = mount(AdminChatConversationDetails, {
      props: { conversation: { ...conversation, status: 'deleted' } },
    });
    expect((deletedWrapper.get('[data-testid="conversation-close-button"]').element as HTMLButtonElement).disabled).toBe(true);
    expect((deletedWrapper.get('[data-testid="conversation-archive-button"]').element as HTMLButtonElement).disabled).toBe(true);

    const loadingWrapper = mount(AdminChatConversationDetails, {
      props: { conversation, lifecycleLoading: true },
    });
    expect((loadingWrapper.get('[data-testid="conversation-close-button"]').element as HTMLButtonElement).disabled).toBe(true);
    expect((loadingWrapper.get('[data-testid="conversation-archive-button"]').element as HTMLButtonElement).disabled).toBe(true);
  });

  it('does not render sensitive fields text', () => {
    const wrapper = mount(AdminChatConversationDetails, {
      props: { conversation },
    });
    const text = wrapper.text();
    expect(text).not.toContain('token');
    expect(text).not.toContain('secret');
    expect(text).not.toContain('device_key');
    expect(text).not.toContain('user_agent');
    expect(text).not.toContain('ip_address');
    expect(text).not.toContain('metadata');
  });
});
