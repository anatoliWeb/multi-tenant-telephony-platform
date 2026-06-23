import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AdminChatWebhookDeliveryStatus from './AdminChatWebhookDeliveryStatus.vue';

const globalStubs = {
  BaseLoader: { template: '<div data-testid="loader">loader</div>', props: ['label'] },
  BaseErrorState: { template: '<div data-testid="error">{{ title }} {{ description }}</div>', props: ['title', 'description'] },
  BaseEmptyState: { template: '<div data-testid="empty">{{ title }} {{ description }}</div>', props: ['title', 'description'] },
};

describe('AdminChatWebhookDeliveryStatus', () => {
  it('renders loading/empty/error states', async () => {
    const wrapper = mount(AdminChatWebhookDeliveryStatus, {
      props: { items: [], loading: true, error: '' },
      global: { stubs: globalStubs },
    });

    expect(wrapper.find('[data-testid="loader"]').exists()).toBe(true);

    await wrapper.setProps({ loading: false });
    expect(wrapper.find('[data-testid="empty"]').exists()).toBe(true);

    await wrapper.setProps({ error: 'failed' });
    expect(wrapper.find('[data-testid="error"]').exists()).toBe(true);
  });

  it('renders status/attempts/retry/status-code/error safely', () => {
    const wrapper = mount(AdminChatWebhookDeliveryStatus, {
      props: {
        loading: false,
        error: '',
        items: [
          {
            id: 10,
            event_type: 'message.created',
            status: 'retrying',
            attempts: 2,
            max_attempts: 5,
            next_retry_at: '2026-01-01T00:00:00Z',
            last_status_code: 500,
            error_summary: 'Request timeout',
            endpoint_name: 'CRM webhook',
            endpoint_url: 'https://example.test/hook',
            created_at: '2026-01-01T00:00:00Z',
            payload: { secret: 'x' },
            signature: 'secret-signature',
            token_hash: 'hash',
          } as any,
        ],
      },
      global: { stubs: globalStubs },
    });

    const text = wrapper.text();
    expect(text).toContain('message.created');
    expect(text).toContain('retrying');
    expect(text).toContain('Attempts: 2 / 5');
    expect(text).toContain('Last status code: 500');
    expect(text).toContain('Request timeout');
    expect(text).toContain('CRM webhook');

    expect(text).not.toContain('secret-signature');
    expect(text).not.toContain('token_hash');
    expect(text).not.toContain('payload');
    expect(text).not.toContain('secret');
  });
});
