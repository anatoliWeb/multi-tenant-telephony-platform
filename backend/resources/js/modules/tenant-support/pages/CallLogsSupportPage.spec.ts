import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const listCallLogsMock = vi.fn();
const getStatisticsMock = vi.fn();
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
          'common.tenantSupport.callLogs.title': 'Call Logs',
          'common.tenantSupport.callLogs.subtitle': 'Tenant-scoped support view for call history and statistics.',
          'common.tenantSupport.callLogs.totalCalls': 'Total calls',
          'common.tenantSupport.callLogs.answeredCalls': 'Answered',
          'common.tenantSupport.callLogs.missedCalls': 'Missed',
          'common.tenantSupport.fields.total': 'Total',
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
    listCallLogs: listCallLogsMock,
    getCallLogStatistics: getStatisticsMock,
  },
}));

describe('CallLogsSupportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    activeTenantIdRef.value = null;
    listCallLogsMock.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    });
    getStatisticsMock.mockResolvedValue({
      total_calls: 0,
      answered_calls: 0,
      missed_calls: 0,
      answer_rate: 0,
    });
  });

  it('shows a translated tenant-selection prompt before a tenant is selected', async () => {
    const { default: CallLogsSupportPage } = await import('./CallLogsSupportPage.vue');
    const wrapper = mount(CallLogsSupportPage);

    await flushPromises();

    expect(wrapper.text()).toContain('Select a tenant to load support data.');
    expect(wrapper.text()).not.toContain('tenantSupport.selectTenantPrompt');
  });

  it('renders statistics and an empty state when the selected tenant has no call logs', async () => {
    activeTenantIdRef.value = 'tenant-a';
    const { default: CallLogsSupportPage } = await import('./CallLogsSupportPage.vue');
    const wrapper = mount(CallLogsSupportPage);

    await flushPromises();

    expect(listCallLogsMock).toHaveBeenCalled();
    expect(getStatisticsMock).toHaveBeenCalled();
    expect(wrapper.text()).toContain('Call Logs');
    expect(wrapper.text()).toContain('No data yet');
  });
});
