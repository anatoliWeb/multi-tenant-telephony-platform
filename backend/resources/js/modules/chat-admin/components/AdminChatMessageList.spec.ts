import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AdminChatMessageList from './AdminChatMessageList.vue';

const globalStubs = {
  BaseLoader: { template: '<div data-testid="loader">loader</div>', props: ['label'] },
  BaseErrorState: { template: '<div data-testid="error">{{ title }} {{ description }}</div>', props: ['title', 'description'] },
  BaseEmptyState: { template: '<div data-testid="empty">{{ title }} {{ description }}</div>', props: ['title', 'description'] },
};

describe('AdminChatMessageList', () => {
  it('renders message body/status/imported/external/attachments', () => {
    const wrapper = mount(AdminChatMessageList, {
      props: {
        loading: false,
        error: '',
        items: [
          {
            id: 11,
            conversation_id: 1,
            body: 'Hello admin',
            status: 'sent',
            is_imported: true,
            imported_from_conversation_id: 3,
            imported_from_message_id: 5,
            source: 'api',
            external_provider: 'provider-x',
            external_message_id: 'ext-123',
            attachments: [
              {
                id: 44,
                original_name: 'contract.pdf',
                mime_type: 'application/pdf',
                size: 2048,
                status: 'active',
                is_imported: false,
              },
            ],
          },
        ],
      },
      global: { stubs: globalStubs },
    });

    const text = wrapper.text();
    expect(text).toContain('Hello admin');
    expect(text).toContain('sent');
    expect(text).toContain('Imported');
    expect(text).toContain('External API');
    expect(text).toContain('provider-x');
    expect(text).toContain('ext-123');
    expect(text).toContain('contract.pdf');
  });

  it('does not render sensitive fields from payload', () => {
    const wrapper = mount(AdminChatMessageList, {
      props: {
        loading: false,
        error: '',
        items: [
          {
            id: 12,
            conversation_id: 1,
            body: 'Safe body',
            status: 'sent',
            metadata: { secret: true },
            external_payload: { token: 'abc' },
            attachments: [
              {
                id: 55,
                original_name: 'safe.txt',
                disk: 's3',
                path: '/private/path',
                checksum: 'sha256-secret',
                token: 'top-secret-token',
              },
            ],
            device_reads: [
              {
                user_id: 7,
                read_at: '2026-01-01T00:00:00Z',
                device_type: 'mobile',
                user_agent: 'UA',
                ip_address: '127.0.0.1',
              },
            ],
          },
        ],
      },
      global: { stubs: globalStubs },
    });

    const text = wrapper.text();
    expect(text).not.toContain('secret');
    expect(text).not.toContain('token');
    expect(text).not.toContain('s3');
    expect(text).not.toContain('/private/path');
    expect(text).not.toContain('sha256-secret');
    expect(text).not.toContain('127.0.0.1');
    expect(text).not.toContain('UA');
  });

  it('delete button renders for non-deleted and asks confirm before emit', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const wrapper = mount(AdminChatMessageList, {
      props: {
        loading: false,
        error: '',
        actionLoadingMessageIds: [],
        items: [{ id: 22, conversation_id: 1, body: 'Delete me', status: 'sent' }],
      },
      global: { stubs: globalStubs },
    });

    await wrapper.get('[data-testid="message-delete-22"]').trigger('click');
    expect(confirmSpy).toHaveBeenCalled();
    expect(wrapper.emitted('delete')?.[0]).toEqual([22]);
    confirmSpy.mockRestore();
  });

  it('delete button is disabled for deleted/loading messages', () => {
    const wrapper = mount(AdminChatMessageList, {
      props: {
        loading: false,
        error: '',
        actionLoadingMessageIds: [24],
        items: [
          { id: 23, conversation_id: 1, body: 'Already deleted', status: 'deleted' },
          { id: 24, conversation_id: 1, body: 'Deleting', status: 'sent' },
        ],
      },
      global: { stubs: globalStubs },
    });

    expect((wrapper.get('[data-testid="message-delete-23"]').element as HTMLButtonElement).disabled).toBe(true);
    expect((wrapper.get('[data-testid="message-delete-24"]').element as HTMLButtonElement).disabled).toBe(true);
    expect(wrapper.text()).toContain('Message deleted');
  });
});
