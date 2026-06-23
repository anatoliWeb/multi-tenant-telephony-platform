import { describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';
import DashboardPage from './DashboardPage.vue';

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
}));

const useCachedRequestMock = vi.hoisted(() => vi.fn());
const hasPermissionMock = vi.hoisted(() => vi.fn());

vi.mock('../../../services/api/client', () => ({
  api: apiMock,
}));

vi.mock('../../../shared/cache', () => ({
  cacheStore: {
    has: vi.fn().mockReturnValue(false),
  },
  useCachedRequest: useCachedRequestMock,
}));

vi.mock('../../../shared/services/realtime/realtime.client', () => ({
  realtimeClient: {
    onSystemNotification: vi.fn(() => () => {}),
  },
}));

vi.mock('../../../stores/auth.store', () => ({
  useAuthStore: () => ({
    hasPermission: hasPermissionMock,
  }),
}));

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
    locale: 'en',
  }),
}));

vi.mock('vue-router', () => ({
  useRoute: () => ({
    fullPath: '/dashboard',
  }),
}));

describe('DashboardPage meta bootstrap loading', () => {
  const baseApiGetMock = () => {
    apiMock.get.mockImplementation(async (url: string) => {
      if (url === '/v1/stats') {
        return { data: { users: 1, admins: 1, managers: 0, tokens: 0, users_with_direct_permissions: 0, recent_activity: [] } };
      }

      if (url === '/v1/meta/bootstrap') {
        return { data: { current_user: { id: 1, name: 'Admin', email: 'admin@example.com', roles: [{ id: 1, name: 'admin' }] }, current_user_permissions: ['users.view'] } };
      }

      return { data: null };
    });
  };

  const baseCachedRequestMock = () => {
    useCachedRequestMock.mockImplementation(async ({ request, onBackgroundUpdate }: { request: () => Promise<unknown>; onBackgroundUpdate?: (value: unknown) => void }) => {
      const data = await request();
      if (onBackgroundUpdate) {
        onBackgroundUpdate(data);
      }
      return { data, revalidating: false };
    });
  };

  it('loads lightweight /v1/meta/bootstrap instead of full /v1/meta', async () => {
    hasPermissionMock.mockReturnValue(false);
    baseApiGetMock();
    baseCachedRequestMock();

    shallowMount(DashboardPage, {
      global: {
        stubs: {
          BaseStatCard: true,
          Doughnut: true,
          Bar: true,
          Line: true,
        },
      },
    });

    await nextTick();
    await nextTick();
    await flushPromises();

    expect(apiMock.get).toHaveBeenCalledWith('/v1/meta/bootstrap');
    expect(apiMock.get).not.toHaveBeenCalledWith('/v1/meta');
  });

  it('shows API docs shortcut when user has api.docs.view permission', async () => {
    hasPermissionMock.mockImplementation((permission: string) => permission === 'api.docs.view');
    baseApiGetMock();
    baseCachedRequestMock();

    const wrapper = shallowMount(DashboardPage, {
      global: {
        stubs: {
          BaseStatCard: true,
          Doughnut: true,
          Bar: true,
          Line: true,
        },
      },
    });

    await nextTick();
    await nextTick();
    await flushPromises();

    const docsCard = wrapper.find('[data-testid="api-docs-card"]');
    const docsLink = wrapper.find('[data-testid="api-docs-link"]');

    expect(docsCard.exists()).toBe(true);
    expect(docsLink.exists()).toBe(true);
    expect(docsLink.attributes('href')).toBe('/docs/api/portal');
    expect(wrapper.text()).toContain('common.apiDocs.dashboardShortcutTitle');
    expect(wrapper.text()).toContain('common.apiDocs.dashboardShortcutDescription');
    expect(wrapper.text()).toContain('common.apiDocs.openDocs');
    expect(wrapper.text()).not.toContain('token');
    expect(wrapper.text()).not.toContain('secret');
  });

  it('hides API docs shortcut when permission is missing', async () => {
    hasPermissionMock.mockReturnValue(false);
    baseApiGetMock();
    baseCachedRequestMock();

    const wrapper = shallowMount(DashboardPage, {
      global: {
        stubs: {
          BaseStatCard: true,
          Doughnut: true,
          Bar: true,
          Line: true,
        },
      },
    });

    await nextTick();
    await nextTick();
    await flushPromises();

    expect(wrapper.find('[data-testid="api-docs-card"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="api-docs-link"]').exists()).toBe(false);
  });

  it('renders safe localized loading/empty-state keys without debug or secret text', async () => {
    hasPermissionMock.mockReturnValue(false);
    useCachedRequestMock.mockImplementation(() => new Promise(() => {}));

    const wrapper = shallowMount(DashboardPage, {
      global: {
        stubs: {
          BaseStatCard: true,
          Doughnut: true,
          Bar: true,
          Line: true,
        },
      },
    });

    await nextTick();

    const text = wrapper.text().toLowerCase();
    expect(text).toContain('common.dashboardpage.loadinganalytics');
    expect(text).not.toContain('token=');
    expect(text).not.toContain('secret');
    expect(text).not.toContain('debug');
  });
});
