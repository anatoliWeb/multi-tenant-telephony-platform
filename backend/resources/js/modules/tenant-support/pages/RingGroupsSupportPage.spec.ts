import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const listRingGroupsMock = vi.fn();
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
          'common.tenantSupport.ringGroups.title': 'Ring Groups',
          'common.tenantSupport.ringGroups.subtitle': 'Tenant-scoped support view for ring-group routing configuration and members.',
          'common.tenantSupport.fields.total': 'Total',
          'common.tenantSupport.fields.name': 'Name',
          'common.tenantSupport.fields.strategy': 'Strategy',
          'common.tenantSupport.fields.status': 'Status',
          'common.tenantSupport.ringGroups.fields.members': 'Members',
          'common.tenantSupport.ringGroups.fields.activeMembers': 'Active members',
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
    listRingGroups: listRingGroupsMock,
  },
}));

describe('RingGroupsSupportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    activeTenantIdRef.value = null;
    listRingGroupsMock.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    });
  });

  it('does not request ring groups before a tenant is selected', async () => {
    const { default: RingGroupsSupportPage } = await import('./RingGroupsSupportPage.vue');
    const wrapper = mount(RingGroupsSupportPage);

    await flushPromises();

    expect(listRingGroupsMock).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Select a tenant to load support data.');
    expect(wrapper.text()).not.toContain('common.tenantSupport.selectTenantPrompt');
  });

  it('requests ring groups after a tenant is selected', async () => {
    activeTenantIdRef.value = 'tenant-a';
    const { default: RingGroupsSupportPage } = await import('./RingGroupsSupportPage.vue');
    const wrapper = mount(RingGroupsSupportPage);

    await flushPromises();

    expect(listRingGroupsMock).toHaveBeenCalled();
    expect(wrapper.text()).toContain('No data yet');
  });

  it('renders ring group members in the support table', async () => {
    activeTenantIdRef.value = 'tenant-a';
    listRingGroupsMock.mockResolvedValue({
      data: [
        {
          id: 1,
          uuid: 'ring-group-1',
          name: 'Sales Ring Group',
          slug: 'sales-ring-group',
          description: null,
          strategy: 'sequential',
          status: 'active',
          ring_timeout_seconds: 20,
          max_ring_duration_seconds: 120,
          members_count: 2,
          active_members_count: 2,
          members: [
            {
              id: 11,
              uuid: 'member-1',
              member_type: 'extension',
              priority: 1,
              delay_seconds: 0,
              timeout_seconds: 20,
              is_active: true,
              extension: { id: 21, number: '2001', label: 'Sales' },
              user: null,
            },
            {
              id: 12,
              uuid: 'member-2',
              member_type: 'user',
              priority: 2,
              delay_seconds: 5,
              timeout_seconds: 25,
              is_active: true,
              extension: null,
              user: { id: 31, name: 'Agent One', email: 'agent@example.test' },
            },
          ],
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    });

    const { default: RingGroupsSupportPage } = await import('./RingGroupsSupportPage.vue');
    const wrapper = mount(RingGroupsSupportPage);

    await flushPromises();

    expect(wrapper.text()).toContain('Sales Ring Group');
    expect(wrapper.text()).toContain('2001 Sales');
    expect(wrapper.text()).toContain('Agent One');
    expect(wrapper.text()).toContain('2 / 2');
  });
});
