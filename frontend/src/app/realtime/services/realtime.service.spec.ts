import { describe, expect, it, vi } from 'vitest';
import { RealtimeService } from './realtime.service';
import { AuthTokenStorageService } from '../../auth/services/auth-token-storage.service';
import { AuthStateService } from '../../core/services/auth-state.service';

describe('RealtimeService', () => {
  it('uses configured authEndpoint and bearer auth header', () => {
    const config: any = {
      production: false,
      realtime: {
        enabled: true,
        provider: 'reverb',
        appKey: 'app-key',
        wsHost: 'localhost',
        wsPort: 6001,
        forceTLS: false,
        usePrivateChannel: true,
        broadcastingAuthUrl: 'http://localhost:8080/broadcasting/auth',
      },
    };

    const tokenStorage = { getToken: vi.fn().mockReturnValue('token-123') } as unknown as AuthTokenStorageService;
    const authState = { userId: 5 } as unknown as AuthStateService;
    const service = new RealtimeService(config, tokenStorage, authState);

    expect((service as any).resolveBroadcastingAuthEndpoint()).toBe('http://localhost:8080/broadcasting/auth');
    expect((service as any).resolveAuthHeaders()).toEqual({
      Accept: 'application/json',
      Authorization: 'Bearer token-123',
    });
  });
});
