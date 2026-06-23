import { flushPromises, shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const hydrateSessionMock = vi.fn();
const hasPermissionMock = vi.fn(() => true);
const hasAnyPermissionMock = vi.fn(() => true);
const routerReplaceMock = vi.fn();
const loadUnreadCountMock = vi.fn(() => Promise.resolve());
const getUnreadConversationsCountMock = vi.fn(() => Promise.resolve(3));
const initRealtimeBridgeMock = vi.fn();
const connectMock = vi.fn();
const getMetricsMock = vi.fn(() => []);
const onStatusChangeMock = vi.fn(() => () => undefined);
const onSystemNotificationMock = vi.fn(() => () => undefined);
const joinPresenceMock = vi.fn(() => () => undefined);
const disconnectMock = vi.fn();
const getDiagnosticsMock = vi.fn(() => ({
  wsConfigured: true,
  appKeyPresent: true,
  authEndpoint: '/broadcasting/auth',
  host: 'localhost',
  port: 6001,
  scheme: 'http',
  forceTLS: false,
  lastConnectionStatus: 'connected',
  lastJoinedChannels: [],
}));

vi.mock('vue-router', () => ({
  useRoute: () => ({ name: 'dashboard' }),
  useRouter: () => ({ replace: routerReplaceMock, push: vi.fn() }),
  RouterLink: {
    name: 'RouterLink',
    props: ['to'],
    template: '<a><slot /></a>',
  },
}));

vi.mock('vue-i18n', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-i18n')>();
  return {
    ...actual,
    useI18n: () => ({
      t: (key: string) => key,
    }),
  };
});

vi.mock('../stores/auth.store', () => ({
  useAuthStore: () => ({
    user: { id: 10, name: 'Admin' },
    permissions: ['notifications.view'],
    hydrateSession: hydrateSessionMock,
    hasPermission: hasPermissionMock,
    hasAnyPermission: hasAnyPermissionMock,
    logout: vi.fn(),
  }),
}));

vi.mock('../stores/translation.store', () => ({
  useTranslationStore: () => ({
    locale: 'en',
    switchLocale: vi.fn(() => Promise.resolve()),
  }),
}));

vi.mock('../modules/notifications/services/notifications.service', () => ({
  notificationsService: {
    unreadCount: { value: 0 },
    initRealtimeBridge: initRealtimeBridgeMock,
    loadUnreadCount: loadUnreadCountMock,
    disposeRealtimeBridge: vi.fn(),
  },
}));

vi.mock('../modules/chat-admin/services/chat-admin.service', () => ({
  chatAdminService: {
    getUnreadConversationsCount: getUnreadConversationsCountMock,
  },
}));

vi.mock('../shared/services/realtime/realtime.client', () => ({
  realtimeClient: {
    connect: connectMock,
    getMetrics: getMetricsMock,
    onStatusChange: onStatusChangeMock,
    onSystemNotification: onSystemNotificationMock,
    joinPresence: joinPresenceMock,
    disconnect: disconnectMock,
    getDiagnostics: getDiagnosticsMock,
  },
}));

vi.mock('../shared/services/realtime/realtime.channels', () => ({
  REALTIME_CHANNELS: {
    presenceOnline: 'presence-online',
    presenceDashboard: 'presence-dashboard',
  },
}));

describe('AdminLayout auth bootstrap guard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('redirects to login and skips protected loading when hydrate fails', async () => {
    hydrateSessionMock.mockResolvedValue(false);
    const { default: AdminLayout } = await import('./AdminLayout.vue');

    shallowMount(AdminLayout, {
      global: {
        stubs: {
          BaseIconButton: true,
          BaseLanguageSwitcher: true,
          BaseRealtimeStatus: true,
          BaseTopbarSearch: true,
          BaseUserDropdown: true,
          RouterView: true,
          'router-link': true,
        },
      },
    });
    await flushPromises();

    expect(routerReplaceMock).toHaveBeenCalledWith('/login');
    expect(loadUnreadCountMock).not.toHaveBeenCalled();
    expect(getUnreadConversationsCountMock).not.toHaveBeenCalled();
    expect(connectMock).not.toHaveBeenCalled();
  }, 10000);

  it('starts realtime and unread loading after successful hydrate', async () => {
    hydrateSessionMock.mockResolvedValue(true);
    getMetricsMock
      .mockReturnValueOnce([
        { key: 'backend_online', label: 'WS', count: 0, active: false },
      ])
      .mockReturnValue([
        { key: 'backend_online', label: 'WS', count: 1, active: true },
      ]);
    onStatusChangeMock.mockImplementation((listener: (state: { status: string }) => void) => {
      listener({ status: 'connected' });
      return () => undefined;
    });
    const { default: AdminLayout } = await import('./AdminLayout.vue');

    shallowMount(AdminLayout, {
      global: {
        stubs: {
          BaseIconButton: true,
          BaseLanguageSwitcher: true,
          BaseRealtimeStatus: true,
          BaseTopbarSearch: true,
          BaseUserDropdown: true,
          RouterView: true,
          'router-link': true,
        },
      },
    });
    await flushPromises();

    expect(connectMock).toHaveBeenCalled();
    expect(joinPresenceMock).toHaveBeenCalledTimes(2);
    expect(joinPresenceMock).toHaveBeenCalledWith('presence-online', expect.any(Object));
    expect(joinPresenceMock).toHaveBeenCalledWith('presence-dashboard', expect.any(Object));
    expect(initRealtimeBridgeMock).toHaveBeenCalled();
    expect(loadUnreadCountMock).toHaveBeenCalled();
    expect(getUnreadConversationsCountMock).toHaveBeenCalled();
    expect(getMetricsMock).toHaveBeenCalled();
  });

  it('hides chat navigation item when admin chat permissions are missing', async () => {
    hydrateSessionMock.mockResolvedValue(true);
    hasAnyPermissionMock.mockImplementation((permissions: string[]) => {
      if (permissions.includes('chat.admin.view') || permissions.includes('chat.admin.view_metadata')) {
        return false;
      }
      return true;
    });

    const { default: AdminLayout } = await import('./AdminLayout.vue');
    const wrapper = shallowMount(AdminLayout, {
      global: {
        stubs: {
          BaseIconButton: true,
          BaseLanguageSwitcher: true,
          BaseRealtimeStatus: true,
          BaseTopbarSearch: true,
          BaseUserDropdown: true,
          RouterView: true,
          'router-link': {
            template: '<a><slot /></a>',
          },
        },
      },
    });

    await flushPromises();

    expect(wrapper.text()).not.toContain('common.chat');
  });

  it('shows chat navigation item when admin chat permission is available', async () => {
    hydrateSessionMock.mockResolvedValue(true);
    hasAnyPermissionMock.mockImplementation((permissions: string[]) => {
      if (permissions.includes('chat.admin.view') || permissions.includes('chat.admin.view_metadata')) {
        return true;
      }
      return true;
    });

    const { default: AdminLayout } = await import('./AdminLayout.vue');
    const wrapper = shallowMount(AdminLayout, {
      global: {
        stubs: {
          BaseIconButton: true,
          BaseLanguageSwitcher: true,
          BaseRealtimeStatus: true,
          BaseTopbarSearch: true,
          BaseUserDropdown: true,
          RouterView: true,
          'router-link': {
            template: '<a><slot /></a>',
          },
        },
      },
    });

    await flushPromises();

    expect(wrapper.text()).toContain('common.chat');
  });

  it('shows API documentation sidebar link when api.docs.view permission is available', async () => {
    hydrateSessionMock.mockResolvedValue(true);
    hasPermissionMock.mockImplementation((permission: string) => {
      if (permission === 'api.docs.view') {
        return true;
      }
      return true;
    });

    const { default: AdminLayout } = await import('./AdminLayout.vue');
    const wrapper = shallowMount(AdminLayout, {
      global: {
        stubs: {
          BaseIconButton: true,
          BaseLanguageSwitcher: true,
          BaseRealtimeStatus: true,
          BaseTopbarSearch: true,
          BaseUserDropdown: true,
          RouterView: true,
          'router-link': {
            template: '<a><slot /></a>',
          },
        },
      },
    });

    await flushPromises();

    const docsLink = wrapper.find('[data-testid="api-docs-sidebar-link"]');
    expect(docsLink.exists()).toBe(true);
    expect(docsLink.attributes('href')).toBe('/docs/api/portal');
    expect(wrapper.text()).toContain('common.apiDocs.sidebarLabel');
    expect(wrapper.text()).toContain('common.tokens');
    expect(wrapper.text().toLowerCase()).not.toContain('token=');
    expect(wrapper.text().toLowerCase()).not.toContain('secret');
  });

  it('hides API documentation sidebar link without api.docs.view permission', async () => {
    hydrateSessionMock.mockResolvedValue(true);
    hasPermissionMock.mockImplementation((permission: string) => permission !== 'api.docs.view');

    const { default: AdminLayout } = await import('./AdminLayout.vue');
    const wrapper = shallowMount(AdminLayout, {
      global: {
        stubs: {
          BaseIconButton: true,
          BaseLanguageSwitcher: true,
          BaseRealtimeStatus: true,
          BaseTopbarSearch: true,
          BaseUserDropdown: true,
          RouterView: true,
          'router-link': {
            template: '<a><slot /></a>',
          },
        },
      },
    });

    await flushPromises();

    expect(wrapper.find('[data-testid="api-docs-sidebar-link"]').exists()).toBe(false);
  });

  it('shows users roles permissions links only when matching permissions are present', async () => {
    hydrateSessionMock.mockResolvedValue(true);
    hasPermissionMock.mockImplementation((permission: string) => {
      return ['users.view', 'roles.view', 'permissions.view'].includes(permission);
    });
    hasAnyPermissionMock.mockImplementation((permissions: string[]) => {
      if (permissions.includes('chat.admin.view') || permissions.includes('chat.admin.view_metadata')) {
        return false;
      }
      return true;
    });

    const { default: AdminLayout } = await import('./AdminLayout.vue');
    const wrapper = shallowMount(AdminLayout, {
      global: {
        stubs: {
          BaseIconButton: true,
          BaseLanguageSwitcher: true,
          BaseRealtimeStatus: true,
          BaseTopbarSearch: true,
          BaseUserDropdown: true,
          RouterView: true,
          'router-link': {
            template: '<a><slot /></a>',
          },
        },
      },
    });

    await flushPromises();

    expect(wrapper.text()).toContain('common.users');
    expect(wrapper.text()).toContain('common.roles');
    expect(wrapper.text()).toContain('common.permissions');
    expect(wrapper.text()).not.toContain('common.chat');
    expect(wrapper.find('[data-testid="api-docs-sidebar-link"]').exists()).toBe(false);
  });

  it('hides users roles permissions links when permissions are missing', async () => {
    hydrateSessionMock.mockResolvedValue(true);
    hasPermissionMock.mockReturnValue(false);
    hasAnyPermissionMock.mockReturnValue(false);

    const { default: AdminLayout } = await import('./AdminLayout.vue');
    const wrapper = shallowMount(AdminLayout, {
      global: {
        stubs: {
          BaseIconButton: true,
          BaseLanguageSwitcher: true,
          BaseRealtimeStatus: true,
          BaseTopbarSearch: true,
          BaseUserDropdown: true,
          RouterView: true,
          'router-link': {
            template: '<a><slot /></a>',
          },
        },
      },
    });

    await flushPromises();

    expect(wrapper.text()).not.toContain('common.users');
    expect(wrapper.text()).not.toContain('common.roles');
    expect(wrapper.text()).not.toContain('common.permissions');
    expect(wrapper.text()).not.toContain('common.chat');
    expect(wrapper.find('[data-testid="api-docs-sidebar-link"]').exists()).toBe(false);
  });

  it('renders realtime diagnostics counters for WS EV ON PG', async () => {
    hydrateSessionMock.mockResolvedValue(true);
    getMetricsMock.mockReturnValue([
      { key: 'backend_online', label: 'WS', count: 1, active: true },
      { key: 'frontend_online', label: 'EV', count: 3, active: true },
      { key: 'presence_online', label: 'ON', count: 2, active: true },
      { key: 'presence_dashboard', label: 'PG', count: 2, active: true },
    ]);

    const { default: AdminLayout } = await import('./AdminLayout.vue');
    const wrapper = shallowMount(AdminLayout, {
      global: {
        stubs: {
          BaseIconButton: true,
          BaseLanguageSwitcher: true,
          BaseRealtimeStatus: {
            props: ['label', 'count'],
            template: '<span class="metric">{{ label }}:{{ count }}</span>',
          },
          BaseTopbarSearch: true,
          BaseUserDropdown: true,
          RouterView: true,
          'router-link': true,
        },
      },
    });

    await flushPromises();

    const text = wrapper.text();
    expect(text).toContain('WS:1');
    expect(text).toContain('EV:3');
    expect(text).toContain('ON:2');
    expect(text).toContain('PG:2');
    expect(text.toLowerCase()).not.toContain('token=');
    expect(text.toLowerCase()).not.toContain('secret');
  });
});
