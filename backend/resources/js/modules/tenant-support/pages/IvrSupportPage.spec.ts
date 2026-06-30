import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const listIvrMenusMock = vi.fn();
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
          'common.tenantSupport.ivr.title': 'IVR',
          'common.tenantSupport.ivr.subtitle': 'Tenant-scoped support view for IVR menus, options, and route summaries.',
          'common.tenantSupport.fields.total': 'Total',
          'common.tenantSupport.fields.name': 'Name',
          'common.tenantSupport.fields.status': 'Status',
          'common.tenantSupport.ivr.fields.options': 'Options',
          'common.tenantSupport.ivr.fields.timeoutRouting': 'Timeout routing',
          'common.tenantSupport.ivr.fields.invalidRouting': 'Invalid routing',
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
    listIvrMenus: listIvrMenusMock,
  },
}));

describe('IvrSupportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    activeTenantIdRef.value = null;
    listIvrMenusMock.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    });
  });

  it('does not request ivr menus before a tenant is selected', async () => {
    const { default: IvrSupportPage } = await import('./IvrSupportPage.vue');
    const wrapper = mount(IvrSupportPage);

    await flushPromises();

    expect(listIvrMenusMock).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Select a tenant to load support data.');
  });

  it('requests ivr menus after a tenant is selected', async () => {
    activeTenantIdRef.value = 'tenant-a';
    const { default: IvrSupportPage } = await import('./IvrSupportPage.vue');
    const wrapper = mount(IvrSupportPage);

    await flushPromises();

    expect(listIvrMenusMock).toHaveBeenCalled();
    expect(wrapper.text()).toContain('No data yet');
  });

  it('renders ivr menu rows in the support table', async () => {
    activeTenantIdRef.value = 'tenant-a';
    listIvrMenusMock.mockResolvedValue({
      data: [
        {
          id: 1,
          uuid: 'ivr-menu-1',
          name: 'Main IVR',
          slug: 'main-ivr',
          status: 'active',
          options_count: 2,
          active_options_count: 2,
          timeout_destination_summary: 'ring_group:12',
          invalid_destination_summary: 'repeat',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    });

    const { default: IvrSupportPage } = await import('./IvrSupportPage.vue');
    const wrapper = mount(IvrSupportPage);

    await flushPromises();

    expect(wrapper.text()).toContain('Main IVR');
    expect(wrapper.text()).toContain('main-ivr');
    expect(wrapper.text()).toContain('2 / 2');
    expect(wrapper.text()).toContain('ring_group:12');
    expect(wrapper.text()).toContain('repeat');
  });
});
