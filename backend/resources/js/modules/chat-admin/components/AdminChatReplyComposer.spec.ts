import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AdminChatReplyComposer from './AdminChatReplyComposer.vue';

const activeConversation = {
  id: 7,
  status: 'active',
};

describe('AdminChatReplyComposer', () => {
  it('renders textarea/send and blocks empty body', async () => {
    const wrapper = mount(AdminChatReplyComposer, {
      props: {
        conversation: activeConversation as any,
        sending: false,
        error: '',
      },
    });

    expect(wrapper.get('[data-testid="reply-textarea"]').exists()).toBe(true);
    expect(wrapper.get('[data-testid="reply-send"]').exists()).toBe(true);
    expect((wrapper.get('[data-testid="reply-send"]').element as HTMLButtonElement).disabled).toBe(true);

    await wrapper.get('[data-testid="reply-textarea"]').setValue('hello');
    expect((wrapper.get('[data-testid="reply-send"]').element as HTMLButtonElement).disabled).toBe(false);
  });

  it('Enter emits submit and Shift+Enter does not submit', async () => {
    const wrapper = mount(AdminChatReplyComposer, {
      props: {
        conversation: activeConversation as any,
        sending: false,
        error: '',
      },
    });

    const textarea = wrapper.get('[data-testid="reply-textarea"]');
    await textarea.setValue('line');
    await textarea.trigger('keydown', { key: 'Enter', shiftKey: true });
    expect(wrapper.emitted('submit')).toBeFalsy();

    await textarea.trigger('keydown', { key: 'Enter', shiftKey: false });
    expect(wrapper.emitted('submit')?.[0]).toEqual([{ body: 'line', type: 'text' }]);
  });

  it('disables for closed/archived/deleted and read-only access', async () => {
    const wrapper = mount(AdminChatReplyComposer, {
      props: {
        conversation: { id: 1, status: 'closed' } as any,
        sending: false,
        error: '',
      },
    });
    expect((wrapper.get('[data-testid="reply-textarea"]').element as HTMLTextAreaElement).disabled).toBe(true);

    await wrapper.setProps({ conversation: { id: 1, status: 'archived' } });
    expect((wrapper.get('[data-testid="reply-textarea"]').element as HTMLTextAreaElement).disabled).toBe(true);

    await wrapper.setProps({ conversation: { id: 1, status: 'deleted' } });
    expect((wrapper.get('[data-testid="reply-textarea"]').element as HTMLTextAreaElement).disabled).toBe(true);

    await wrapper.setProps({ conversation: { id: 1, status: 'active', current_user_access: { access_state: 'read_only' } } });
    expect((wrapper.get('[data-testid="reply-textarea"]').element as HTMLTextAreaElement).disabled).toBe(true);
  });

  it('renders safe error and does not render sensitive fields', () => {
    const wrapper = mount(AdminChatReplyComposer, {
      props: {
        conversation: activeConversation as any,
        sending: false,
        error: 'Failed to send message.',
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Failed to send message.');
    expect(text).not.toContain('token');
    expect(text).not.toContain('secret');
    expect(text).not.toContain('user_agent');
    expect(text).not.toContain('ip_address');
    expect(text).not.toContain('device_key');
  });
});

