import { of } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { ApiClientService } from './api-client.service';

describe('ApiClientService', () => {
  it('resolves url from base api config and forwards query params', () => {
    const getMock = vi.fn().mockReturnValue(of({ data: { ok: true } }));
    const httpClient = { get: getMock } as any;
    const service = new ApiClientService(httpClient, {
      production: false,
      apiBaseUrl: 'http://localhost:8080/api/',
    } as any);

    service.get('/v1/meta/bootstrap', {
      params: {
        locale: 'en',
        page: 2,
        enabled: true,
      },
    });

    expect(getMock).toHaveBeenCalledTimes(1);
    const [url, options] = getMock.mock.calls[0];
    expect(url).toBe('http://localhost:8080/api/v1/meta/bootstrap');
    expect(options.params.get('locale')).toBe('en');
    expect(options.params.get('page')).toBe('2');
    expect(options.params.get('enabled')).toBe('true');
  });

  it('does not expose token or secret values in resolved request url', () => {
    const getMock = vi.fn().mockReturnValue(of({ data: {} }));
    const service = new ApiClientService(
      { get: getMock } as any,
      {
        production: false,
        apiBaseUrl: 'http://localhost:8080/api',
      } as any,
    );

    service.get('v1/dashboard');

    const [url] = getMock.mock.calls[0];
    const lower = String(url).toLowerCase();
    expect(lower).not.toContain('token');
    expect(lower).not.toContain('secret');
    expect(lower).toBe('http://localhost:8080/api/v1/dashboard');
  });
});

