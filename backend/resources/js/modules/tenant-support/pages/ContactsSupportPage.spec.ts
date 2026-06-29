import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const listContactsMock = vi.fn();
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
    listContacts: listContactsMock,
  },
}));

describe('ContactsSupportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    activeTenantIdRef.value = null;
    listContactsMock.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    });
  });

  it('does not request contacts before a tenant is selected', async () => {
    const { default: ContactsSupportPage } = await import('./ContactsSupportPage.vue');
    const wrapper = mount(ContactsSupportPage);

    await flushPromises();

    expect(listContactsMock).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Select a tenant to load support data.');
    expect(wrapper.text()).not.toContain('tenantSupport.selectTenantPrompt');
  });

  it('requests contacts after a tenant is selected', async () => {
    activeTenantIdRef.value = 'tenant-a';
    const { default: ContactsSupportPage } = await import('./ContactsSupportPage.vue');
    mount(ContactsSupportPage);

    await flushPromises();

    expect(listContactsMock).toHaveBeenCalled();
  });

  it('renders an empty state instead of a blank table when no contacts exist', async () => {
    activeTenantIdRef.value = 'tenant-a';
    const { default: ContactsSupportPage } = await import('./ContactsSupportPage.vue');
    const wrapper = mount(ContactsSupportPage);

    await flushPromises();

    expect(wrapper.text()).toContain('No data yet');
  });
});
