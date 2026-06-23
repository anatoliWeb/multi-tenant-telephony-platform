import { beforeEach, describe, expect, it, vi } from 'vitest';

const bindMock = vi.fn();
const privateListenMock = vi.fn().mockReturnThis();
const publicListenMock = vi.fn().mockReturnThis();
const joinHereMock = vi.fn();
const joinJoiningMock = vi.fn();
const joinLeavingMock = vi.fn();
const joinErrorMock = vi.fn();
const joinMock = vi.fn().mockReturnValue({
  here: joinHereMock,
  joining: joinJoiningMock,
  leaving: joinLeavingMock,
  error: joinErrorMock,
});
const leaveMock = vi.fn();
const disconnectMock = vi.fn();

const ctorArgs: unknown[] = [];

vi.mock('laravel-echo', () => {
  return {
    default: class EchoMock {
      public connector = {
        pusher: {
          connection: {
            bind: bindMock,
          },
        },
      };

      constructor(options: unknown) {
        ctorArgs.push(options);
      }

      private() {
        return {
          listen: privateListenMock,
        };
      }

      channel() {
        return {
          listen: publicListenMock,
        };
      }

      join() {
        return joinMock();
      }

      leave() {
        leaveMock();
      }

      disconnect() {
        disconnectMock();
      }
    },
  };
});

vi.mock('pusher-js', () => ({
  default: class PusherMock {},
}));

describe('RealtimeClient auth headers', async () => {
  const { RealtimeClient } = await import('./realtime.client');
  const { REALTIME_CHANNELS } = await import('./realtime.channels');

  beforeEach(() => {
    window.localStorage.clear();
    ctorArgs.length = 0;
    bindMock.mockClear();
    privateListenMock.mockClear();
    publicListenMock.mockClear();
    joinMock.mockClear();
    joinHereMock.mockReset();
    joinJoiningMock.mockReset();
    joinLeavingMock.mockReset();
    joinErrorMock.mockReset();
    leaveMock.mockClear();
    disconnectMock.mockClear();
    vi.stubEnv('VITE_REVERB_APP_KEY', 'app-key');
    vi.stubEnv('VITE_REVERB_HOST', 'localhost');
    vi.stubEnv('VITE_REVERB_PORT', '6001');
    vi.stubEnv('VITE_REVERB_SCHEME', 'http');
  });

  it('adds bearer token and accept header for broadcasting auth', () => {
    window.localStorage.setItem('admin_access_token', 'token-123');
    const client = new RealtimeClient();
    client.connect();

    const options = ctorArgs[0] as { auth?: { headers?: Record<string, string> } };
    expect(options.auth?.headers?.Authorization).toBe('Bearer token-123');
    expect(options.auth?.headers?.Accept).toBe('application/json');
  });

  it('keeps accept header without bearer when token is absent', () => {
    const client = new RealtimeClient();
    client.connect();

    const options = ctorArgs[0] as { auth?: { headers?: Record<string, string> } };
    expect(options.auth?.headers?.Accept).toBe('application/json');
    expect(options.auth?.headers?.Authorization).toBeUndefined();
  });

  it('uses local dev fallback app key when VITE_REVERB_APP_KEY is missing', () => {
    vi.stubEnv('VITE_REVERB_APP_KEY', '');

    const client = new RealtimeClient();
    client.connect();

    const options = ctorArgs[0] as { key?: string };
    expect(options.key).toBe('app-key');
  });

  it('exposes safe diagnostics snapshot without sensitive credentials', () => {
    const client = new RealtimeClient();
    client.connect();

    const diagnostics = client.getDiagnostics();
    expect(diagnostics.authEndpoint).toBe('/broadcasting/auth');
    expect(diagnostics.host).toBe('localhost');
    expect(diagnostics.port).toBe(6001);
    expect(diagnostics.appKeyPresent).toBe(true);
    expect(diagnostics.lastJoinedChannels).toEqual([]);
    expect(JSON.stringify(diagnostics)).not.toContain('token-123');
  });

  it('updates websocket metric when connection events fire', () => {
    const client = new RealtimeClient();
    client.connect();

    const connectedHandler = bindMock.mock.calls.find((call) => call[0] === 'connected')?.[1] as (() => void);
    const disconnectedHandler = bindMock.mock.calls.find((call) => call[0] === 'disconnected')?.[1] as (() => void);

    connectedHandler();
    let metrics = client.getMetrics();
    expect(metrics.find((item) => item.key === 'backend_online')?.count).toBe(1);

    disconnectedHandler();
    metrics = client.getMetrics();
    expect(metrics.find((item) => item.key === 'backend_online')?.count).toBe(0);
  });

  it('updates presence metrics and events counter on presence callbacks', () => {
    const client = new RealtimeClient();
    client.connect();

    const unsubscribe = client.joinPresence(REALTIME_CHANNELS.presenceOnline, {});

    const hereHandler = joinHereMock.mock.calls[0]?.[0] as ((users: Array<{ id: number; name: string }>) => void);
    const joiningHandler = joinJoiningMock.mock.calls[0]?.[0] as ((user: { id: number; name: string }) => void);
    const leavingHandler = joinLeavingMock.mock.calls[0]?.[0] as ((user: { id: number; name: string }) => void);

    hereHandler([{ id: 10, name: 'Admin' }]);
    let metrics = client.getMetrics();
    expect(metrics.find((item) => item.key === 'presence_online')?.count).toBe(1);
    expect(metrics.find((item) => item.key === 'presence_dashboard')?.count).toBe(1);

    joiningHandler({ id: 11, name: 'Member' });
    metrics = client.getMetrics();
    expect(metrics.find((item) => item.key === 'presence_online')?.count).toBe(2);

    leavingHandler({ id: 10, name: 'Admin' });
    metrics = client.getMetrics();
    expect(metrics.find((item) => item.key === 'presence_online')?.count).toBe(1);
    expect(client.getState().eventsReceived).toBeGreaterThan(0);

    unsubscribe();
    metrics = client.getMetrics();
    expect(metrics.find((item) => item.key === 'presence_dashboard')?.count).toBe(0);
  });
});
