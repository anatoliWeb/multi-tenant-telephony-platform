import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

const requestUseMock = vi.fn();
const axiosInstanceMock = {
  interceptors: {
    request: {
      use: requestUseMock,
    },
  },
};
const createMock = vi.fn(() => axiosInstanceMock);

vi.mock('axios', () => ({
  default: {
    create: createMock,
  },
}));

vi.mock('./interceptors', () => ({
  attachInterceptors: vi.fn(),
}));

describe('http auth interceptor', () => {
  let interceptor: (config: Record<string, any>) => Record<string, any>;

  beforeAll(async () => {
    await import('./http');
    interceptor = requestUseMock.mock.calls[0][0] as (config: Record<string, any>) => Record<string, any>;
  });

  beforeEach(() => {
    vi.clearAllMocks();
    window.localStorage.clear();
  });

  it('adds Authorization header when token exists', async () => {
    window.localStorage.setItem('admin_access_token', 'token-abc');
    const config = interceptor({ headers: {} });

    expect(config.headers.Authorization).toBe('Bearer token-abc');
  });

  it('adds Authorization via AxiosHeaders-like set method', () => {
    window.localStorage.setItem('admin_access_token', 'token-setter');
    const set = vi.fn();
    const config = interceptor({ headers: { set } });

    expect(set).toHaveBeenCalledWith('Accept-Language', expect.any(String));
    expect(set).toHaveBeenCalledWith('Authorization', 'Bearer token-setter');
    expect(config.headers.set).toBe(set);
  });

  it('does not add Authorization when token is missing', () => {
    const config = interceptor({ headers: {} });

    expect(config.headers.Authorization).toBeUndefined();
  });

  it('reads token lazily at request time', () => {
    const firstConfig = interceptor({ headers: {} });
    expect(firstConfig.headers.Authorization).toBeUndefined();

    window.localStorage.setItem('admin_access_token', 'token-late');
    const secondConfig = interceptor({ headers: {} });
    expect(secondConfig.headers.Authorization).toBe('Bearer token-late');
  });
});
