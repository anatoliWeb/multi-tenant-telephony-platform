import { beforeEach, describe, expect, it, vi } from 'vitest';

const apiMock = vi.hoisted(() => ({
  post: vi.fn(),
  get: vi.fn(),
}));

vi.mock('../api/client', () => ({
  api: apiMock,
}));

describe('authService token/session behavior', async () => {
  const { authService } = await import('./auth.service');

  beforeEach(() => {
    vi.clearAllMocks();
    window.localStorage.clear();
  });

  it('login uses session endpoint as canonical Vue Admin flow', async () => {
    apiMock.post.mockResolvedValue({
      data: {
        user: { id: 1, name: 'Admin', email: 'admin@example.com' },
        permissions: ['chat.view'],
      },
    });

    const payload = await authService.login({
      email: 'admin@example.com',
      password: 'secret',
    });

    expect(apiMock.post).toHaveBeenCalledWith('/v1/auth/session/login', {
      email: 'admin@example.com',
      password: 'secret',
    });
    expect(window.localStorage.getItem('admin_access_token')).toBeNull();
    expect(payload.user?.id).toBe(1);
    expect(payload.permissions).toEqual(['chat.view']);
  });

  it('login keeps existing token untouched for optional bearer fallback', async () => {
    window.localStorage.setItem('admin_access_token', 'persisted-token');
    apiMock.post.mockResolvedValue({
      data: {
        user: { id: 3, name: 'No Token', email: 'no-token@example.com' },
        permissions: [],
      },
    });

    await authService.login({
      email: 'no-token@example.com',
      password: 'secret',
    });

    expect(window.localStorage.getItem('admin_access_token')).toBe('persisted-token');
  });

  it('fetchSession falls back to session/me when bearer is missing', async () => {
    apiMock.get.mockResolvedValueOnce({
      data: {
        user: { id: 2, name: 'Ops', email: 'ops@example.com' },
        permissions: ['notifications.view'],
      },
    });
    await authService.fetchSession();
    expect(apiMock.get).toHaveBeenCalledWith('/v1/auth/session/me');
  });

  it('fetchSession uses token/me first and falls back to session/me on token failure', async () => {
    window.localStorage.setItem('admin_access_token', 'persisted-token');
    apiMock.get
      .mockRejectedValueOnce(new Error('401'))
      .mockResolvedValueOnce({
        data: {
          user: { id: 5, name: 'Persisted', email: 'persisted@example.com' },
          permissions: ['dashboard.view'],
        },
      });

    await authService.fetchSession();

    expect(apiMock.get).toHaveBeenNthCalledWith(1, '/v1/auth/me');
    expect(apiMock.get).toHaveBeenNthCalledWith(2, '/v1/auth/session/me');
    expect(window.localStorage.getItem('admin_access_token')).toBeNull();
  });

  it('hard refresh simulation with valid bearer keeps token path', async () => {
    window.localStorage.setItem('admin_access_token', 'persisted-token');
    apiMock.get.mockResolvedValueOnce({
      data: {
        user: { id: 5, name: 'Persisted', email: 'persisted@example.com' },
        permissions: ['dashboard.view'],
      },
    });

    await authService.fetchSession();

    expect(apiMock.get).toHaveBeenCalledWith('/v1/auth/me');
  });

  it('logout revokes token when present and also calls session logout', async () => {
    window.localStorage.setItem('admin_access_token', 'token-b');
    window.localStorage.setItem('admin_active_tenant_id', 'tenant-z');
    apiMock.post.mockResolvedValue({ data: {} });

    await authService.logout();

    expect(apiMock.post).toHaveBeenCalledWith('/v1/auth/logout', {});
    expect(apiMock.post).toHaveBeenCalledWith('/v1/auth/session/logout', {});
    expect(window.localStorage.getItem('admin_access_token')).toBeNull();
    expect(window.localStorage.getItem('admin_active_tenant_id')).toBeNull();
  });
});
