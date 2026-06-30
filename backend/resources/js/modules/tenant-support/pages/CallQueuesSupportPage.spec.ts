import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const listCallQueuesMock = vi.fn();
const activeTenantIdRef = ref<string | null>(null);

vi.mock('pinia', () => ({
  storeToRefs: () => ({
    activeTenantId: activeTenantIdRef,
  }),
}));

vi.mock('vue-i18n', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-i18n')>();
  return {
    ...actual,
    useI18n: () => ({
      t: (key: string) => (
        {
          'common.tenantSupport.selectTenantPrompt': 'Select a tenant to load support data.',
          'common.generic.noDataYet': 'No data yet',
          'common.loading': 'Loading',
          'common.generic.somethingWentWrong': 'Something went wrong',
          'common.tenantSupport.callQueues.title': 'Call Queues',
          'common.tenantSupport.callQueues.subtitle': 'Tenant-scoped support view for queue configuration and routing state.',
          'common.tenantSupport.fields.total': 'Total',
          'common.tenantSupport.fields.name': 'Name',
          'common.tenantSupport.fields.strategy': 'Strategy',
          'common.tenantSupport.fields.status': 'Status',
          'common.tenantSupport.callQueues.fields.members': 'Members',
          'common.tenantSupport.callQueues.fields.pausedMembers': 'Paused members',
          'common.tenantSupport.callQueues.fields.overflow': 'Overflow',
        }[key] ?? key
      ),
    }),
  };
});

vi.mock('../../../stores/tenant.store', () => ({
  useTenantStore: () => ({}),
}));

vi.mock('../services/tenant-support.service', () => ({
  tenantSupportService: {
    listCallQueues: listCallQueuesMock,
  },
}));

describe('CallQueuesSupportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    activeTenantIdRef.value = null;
    listCallQueuesMock.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    });
  });

  it('does not request call queues before a tenant is selected', async () => {
    const { default: CallQueuesSupportPage } = await import('./CallQueuesSupportPage.vue');
    const wrapper = mount(CallQueuesSupportPage);

    await flushPromises();

    expect(listCallQueuesMock).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Select a tenant to load support data.');
  });

  it('requests call queues after a tenant is selected', async () => {
    activeTenantIdRef.value = 'tenant-a';
    const { default: CallQueuesSupportPage } = await import('./CallQueuesSupportPage.vue');
    const wrapper = mount(CallQueuesSupportPage);

    await flushPromises();

    expect(listCallQueuesMock).toHaveBeenCalled();
    expect(wrapper.text()).toContain('No data yet');
  });

  it('renders call queue rows in the support table', async () => {
    activeTenantIdRef.value = 'tenant-a';
    listCallQueuesMock.mockResolvedValue({
      data: [
        {
          id: 1,
          uuid: 'call-queue-1',
          name: 'Support Queue',
          slug: 'support-queue',
          strategy: 'ring_all',
          status: 'active',
          members_count: 3,
          active_members_count: 2,
          paused_members_count: 1,
          overflow_destination_summary: 'user:1',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    });

    const { default: CallQueuesSupportPage } = await import('./CallQueuesSupportPage.vue');
    const wrapper = mount(CallQueuesSupportPage);

    await flushPromises();

    expect(wrapper.text()).toContain('Support Queue');
    expect(wrapper.text()).toContain('support-queue');
    expect(wrapper.text()).toContain('2 / 3');
    expect(wrapper.text()).toContain('1');
    expect(wrapper.text()).toContain('user:1');
  });
});
