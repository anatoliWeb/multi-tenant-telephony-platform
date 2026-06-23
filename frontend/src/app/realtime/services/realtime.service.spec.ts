import { describe, expect, it, vi } from 'vitest';
import { RealtimeService } from './realtime.service';
import { AuthTokenStorageService } from '../../auth/services/auth-token-storage.service';
import { AuthStateService } from '../../core/services/auth-state.service';

const echoCtorMock = vi.fn();
const joinMock = vi.fn();
const privateMock = vi.fn();
const channelMock = vi.fn();
const leaveMock = vi.fn();

vi.mock('laravel-echo', () => {
  class EchoMock {
    connector = { pusher: { connection: { bind: vi.fn(), state: 'disconnected' } } };
    constructor(config: unknown) {
      echoCtorMock(config);
    }
    join = joinMock;
    private = privateMock;
    channel = channelMock;
    leave = leaveMock;
    disconnect = vi.fn();
  }
  return { default: EchoMock };
});

describe('RealtimeService', () => {
  it('uses configured authEndpoint and bearer auth header', () => {
    privateMock.mockReturnValue({
      subscribed: () => ({ listen: vi.fn() }),
      listen: vi.fn(),
    });
    channelMock.mockReturnValue({
      subscribed: () => ({ listen: vi.fn() }),
    });
    joinMock.mockReturnValue({
      here: () => ({ joining: () => ({ leaving: vi.fn() }) }),
    });

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

    service.connect();

    const ctorConfig = echoCtorMock.mock.calls[0]?.[0] as any;
    expect(ctorConfig.authEndpoint).toBe('http://localhost:8080/broadcasting/auth');
    expect(ctorConfig.auth.headers.Authorization).toBe('Bearer token-123');
    expect(ctorConfig.auth.headers.Accept).toBe('application/json');
  });
});
