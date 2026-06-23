import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { REALTIME_CHANNELS, REALTIME_EVENTS } from './realtime.channels';
import type {
  ActivityStreamPayload,
  RealtimeDiagnosticsState,
  NotificationCreatedPayload,
  RealtimeConnectionState,
  RealtimePresenceState,
  RealtimePresenceUser,
  RealtimeStatusMetric,
  SystemNotificationPayload,
} from './realtime.types';
import { getToken } from '../../../services/auth/token.storage';

/**
 * Websocket-ready realtime client placeholder.
 *
 * WHY PREPARE NOW:
 * The app shell already surfaces live system status. This client gives us a
 * stable integration seam where Laravel Reverb / Echo can be attached later,
 * while current UI keeps using deterministic mock metrics.
 */
type RealtimeListener = (payload: SystemNotificationPayload) => void;
type ActivityListener = (payload: ActivityStreamPayload) => void;
type NotificationCreatedListener = (payload: NotificationCreatedPayload) => void;
type StatusListener = (state: RealtimeConnectionState) => void;
type PresenceCallbacks = {
  here?: (users: RealtimePresenceUser[]) => void;
  joining?: (user: RealtimePresenceUser) => void;
  leaving?: (user: RealtimePresenceUser) => void;
  error?: (error: unknown) => void;
};
type PrivateChannelCallbacks = Record<string, (payload: unknown) => void>;

type ReverbEnv = {
  appKey: string;
  host: string;
  port: number;
  scheme: 'http' | 'https';
  forceTLS: boolean;
  usePrivateChannel: boolean;
};

export class RealtimeClient {
  private echo: Echo<'reverb'> | null = null;
  private state: RealtimeConnectionState = {
    connected: false,
    transport: 'none',
    status: 'disconnected',
    eventsReceived: 0,
  };
  private readonly listeners = new Set<RealtimeListener>();
  private readonly activityListeners = new Set<ActivityListener>();
  private readonly notificationCreatedListeners = new Set<NotificationCreatedListener>();
  private readonly notificationUserSubscriptions = new Set<number>();
  private readonly statusListeners = new Set<StatusListener>();
  private readonly presenceStates = new Map<string, RealtimePresenceState>();
  private readonly joinedPresenceChannels = new Set<string>();
  private diagnostics: RealtimeDiagnosticsState = {
    wsConfigured: false,
    appKeyPresent: false,
    authEndpoint: '/broadcasting/auth',
    host: 'localhost',
    port: 6001,
    scheme: 'http',
    forceTLS: false,
    lastConnectionStatus: 'disconnected',
    lastJoinedChannels: [],
    lastAuthStatus: 'idle',
  };

  connect(): RealtimeConnectionState {
    if (this.echo) {
      return this.getState();
    }

    const env = this.resolveEnv();
    this.updateDiagnosticsFromEnv(env);

    if (!env.appKey) {
      this.updateState({
        connected: false,
        transport: 'none',
        status: 'error',
        lastError: 'Missing VITE_REVERB_APP_KEY',
        lastSyncAt: new Date().toISOString(),
      });

      return this.getState();
    }

    this.state = {
      connected: false,
      transport: 'websocket',
      status: 'connecting',
      eventsReceived: this.state.eventsReceived ?? 0,
      lastSyncAt: new Date().toISOString(),
    };
    this.notifyStatus();

    (window as Window & { Pusher?: typeof Pusher }).Pusher = Pusher;

    this.echo = new Echo({
      broadcaster: 'reverb',
      key: env.appKey,
      wsHost: env.host,
      wsPort: env.port,
      wssPort: env.port,
      forceTLS: env.forceTLS,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/broadcasting/auth',
      withCredentials: true,
      auth: {
        headers: this.resolveAuthHeaders(),
      },
    });

    const connection = this.echo.connector.pusher.connection;
    connection.bind('connected', () => {
      this.diagnostics.lastConnectionStatus = 'connected';
      this.diagnostics.lastConnectionError = undefined;
      this.updateState({
        connected: true,
        transport: 'websocket',
        status: 'connected',
        connectedAt: new Date().toISOString(),
        lastSyncAt: new Date().toISOString(),
        lastError: undefined,
      });
    });
    connection.bind('disconnected', () => {
      this.diagnostics.lastConnectionStatus = 'disconnected';
      this.updateState({
        connected: false,
        status: 'disconnected',
        lastSyncAt: new Date().toISOString(),
      });
    });
    connection.bind('error', (error: unknown) => {
      this.diagnostics.lastConnectionStatus = 'error';
      this.diagnostics.lastConnectionError = this.normalizeError(error);
      this.updateState({
        connected: false,
        status: 'error',
        lastError: this.normalizeError(error),
        lastSyncAt: new Date().toISOString(),
      });
    });

    const notificationChannel = env.usePrivateChannel
      ? this.echo.private(REALTIME_CHANNELS.systemNotificationsPrivate)
      : this.echo.channel(REALTIME_CHANNELS.systemNotificationsPublic);

    notificationChannel.listen(REALTIME_EVENTS.systemNotification, (payload: SystemNotificationPayload) => {
      this.bumpEventsCounter();

      this.listeners.forEach((listener) => listener(payload));
    });

    this.echo
      .private(REALTIME_CHANNELS.activityStreamPrivate)
      .listen(REALTIME_EVENTS.activityLogged, (payload: ActivityStreamPayload) => {
        this.bumpEventsCounter();
        this.activityListeners.forEach((listener) => listener(payload));
      });

    return this.getState();
  }

  disconnect(): void {
    if (!this.echo) {
      return;
    }

    this.echo.leave(`private-${REALTIME_CHANNELS.systemNotificationsPrivate}`);
    this.echo.leave(REALTIME_CHANNELS.systemNotificationsPublic);
    this.echo.leave(`private-${REALTIME_CHANNELS.activityStreamPrivate}`);
    for (const channelName of this.presenceStates.keys()) {
      this.echo.leave(`presence-${channelName}`);
    }
    for (const userId of this.notificationUserSubscriptions.values()) {
      this.echo.leave(`private-${REALTIME_CHANNELS.notificationsUserPrefix}${userId}`);
    }
    this.notificationUserSubscriptions.clear();
    this.presenceStates.clear();
    this.joinedPresenceChannels.clear();
    this.echo.disconnect();
    this.echo = null;

    this.updateState({
      connected: false,
      transport: 'none',
      status: 'disconnected',
      lastSyncAt: new Date().toISOString(),
    });
  }

  getState(): RealtimeConnectionState {
    return { ...this.state };
  }

  getDiagnostics(): RealtimeDiagnosticsState {
    return {
      ...this.diagnostics,
      lastJoinedChannels: [...this.diagnostics.lastJoinedChannels],
    };
  }

  onSystemNotification(listener: RealtimeListener): () => void {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  }

  onStatusChange(listener: StatusListener): () => void {
    this.statusListeners.add(listener);
    listener(this.getState());

    return () => {
      this.statusListeners.delete(listener);
    };
  }

  onActivityLogged(listener: ActivityListener): () => void {
    this.activityListeners.add(listener);

    return () => {
      this.activityListeners.delete(listener);
    };
  }

  onNotificationCreated(userId: number, listener: NotificationCreatedListener): () => void {
    this.notificationCreatedListeners.add(listener);

    if (!this.echo) {
      this.connect();
    }

    if (this.echo) {
      const channelName = `${REALTIME_CHANNELS.notificationsUserPrefix}${userId}`;
      if (!this.notificationUserSubscriptions.has(userId)) {
        this.echo
          .private(channelName)
          .listen(REALTIME_EVENTS.notificationCreated, (payload: NotificationCreatedPayload) => {
            this.bumpEventsCounter();
            this.notificationCreatedListeners.forEach((callback) => callback(payload));
          });
        this.notificationUserSubscriptions.add(userId);
      }
    }

    return () => {
      this.notificationCreatedListeners.delete(listener);
    };
  }

  joinPresence(channelName: string, callbacks: PresenceCallbacks = {}): () => void {
    if (!this.echo) {
      this.connect();
    }

    if (!this.echo) {
      return () => undefined;
    }

    const channel = this.echo.join(channelName);
    this.joinedPresenceChannels.add(channelName);
    this.diagnostics.lastJoinedChannels = [...this.joinedPresenceChannels];
    this.diagnostics.lastAuthStatus = 'ok';

    channel.here((users: RealtimePresenceUser[]) => {
      this.presenceStates.set(channelName, {
        users: [...users],
        count: users.length,
      });
      this.bumpEventsCounter();
      callbacks.here?.(users);
    });

    channel.joining((user: RealtimePresenceUser) => {
      const current = this.presenceStates.get(channelName) ?? { users: [], count: 0 };
      const nextUsers = current.users.some((item) => item.id === user.id)
        ? current.users
        : [...current.users, user];

      this.presenceStates.set(channelName, {
        users: nextUsers,
        count: nextUsers.length,
      });
      this.bumpEventsCounter();
      callbacks.joining?.(user);
    });

    channel.leaving((user: RealtimePresenceUser) => {
      const current = this.presenceStates.get(channelName) ?? { users: [], count: 0 };
      const nextUsers = current.users.filter((item) => item.id !== user.id);

      this.presenceStates.set(channelName, {
        users: nextUsers,
        count: nextUsers.length,
      });
      this.bumpEventsCounter();
      callbacks.leaving?.(user);
    });

    channel.error((error: unknown) => {
      this.diagnostics.lastAuthStatus = 'error';
      this.diagnostics.lastPresenceError = this.normalizeError(error);
      callbacks.error?.(error);
    });

    return () => {
      this.leavePresence(channelName);
    };
  }

  leavePresence(channelName: string): void {
    if (!this.echo) {
      return;
    }

    this.echo.leave(`presence-${channelName}`);
    this.presenceStates.delete(channelName);
    this.joinedPresenceChannels.delete(channelName);
    this.diagnostics.lastJoinedChannels = [...this.joinedPresenceChannels];
  }

  getPresenceState(channelName: string): RealtimePresenceState {
    return this.presenceStates.get(channelName) ?? { users: [], count: 0 };
  }

  subscribeToPrivateChannel(channelName: string, callbacks: PrivateChannelCallbacks): () => void {
    if (!this.echo) {
      this.connect();
    }

    if (!this.echo) {
      return () => undefined;
    }

    const channel = this.echo.private(channelName);
    const events = Object.entries(callbacks);
    events.forEach(([eventName, callback]) => {
      channel.listen(eventName, (payload: unknown) => {
        this.bumpEventsCounter();
        callback(payload);
      });
    });

    return () => {
      if (!this.echo) {
        return;
      }

      events.forEach(([eventName]) => {
        channel.stopListening(eventName);
      });
      this.echo.leave(`private-${channelName}`);
    };
  }

  getMetrics(): RealtimeStatusMetric[] {
    const allPresenceUsers = new Map<number, RealtimePresenceUser>();
    for (const state of this.presenceStates.values()) {
      for (const user of state.users) {
        allPresenceUsers.set(user.id, user);
      }
    }
    const onlinePresence = allPresenceUsers.size;
    const joinedPresenceCount = this.joinedPresenceChannels.size;

    return [
      {
        key: 'backend_online',
        label: 'WS',
        count: this.state.connected ? 1 : 0,
        active: this.state.connected,
      },
      {
        key: 'frontend_online',
        label: 'EV',
        count: this.state.eventsReceived ?? 0,
        active: (this.state.eventsReceived ?? 0) > 0,
      },
      {
        key: 'presence_online',
        label: 'ON',
        count: onlinePresence,
        active: onlinePresence > 0,
      },
      {
        key: 'presence_dashboard',
        label: 'PG',
        count: joinedPresenceCount,
        active: joinedPresenceCount > 0,
      },
    ];
  }

  // Backward-compatible wrapper used by current layout.
  getMockMetrics(): RealtimeStatusMetric[] {
    return this.getMetrics();
  }

  private resolveEnv(): ReverbEnv {
    const runtimeAppKey = String(import.meta.env.VITE_REVERB_APP_KEY ?? '').trim();
    const isLocalRuntime = ['localhost', '127.0.0.1'].includes(window.location.hostname);
    const fallbackDevAppKey = import.meta.env.DEV || isLocalRuntime ? 'app-key' : '';
    const appKey = runtimeAppKey || fallbackDevAppKey;
    const host = String(import.meta.env.VITE_REVERB_HOST ?? window.location.hostname ?? 'localhost');
    const port = Number.parseInt(String(import.meta.env.VITE_REVERB_PORT ?? '6001'), 10);
    const scheme = String(import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https' ? 'https' : 'http';
    const forceTLS = String(import.meta.env.VITE_REVERB_FORCE_TLS ?? '') === 'true' || scheme === 'https';
    const usePrivateChannel = String(import.meta.env.VITE_REVERB_USE_PRIVATE_CHANNEL ?? 'true') !== 'false';

    return {
      appKey,
      host,
      port: Number.isNaN(port) ? 6001 : port,
      scheme,
      forceTLS,
      usePrivateChannel,
    };
  }

  private updateState(update: Partial<RealtimeConnectionState>): void {
    this.state = {
      ...this.state,
      ...update,
    };

    this.notifyStatus();
  }

  private notifyStatus(): void {
    const snapshot = this.getState();
    this.statusListeners.forEach((listener) => listener(snapshot));
  }

  private normalizeError(error: unknown): string {
    if (error instanceof Error) {
      return error.message;
    }

    if (typeof error === 'string') {
      return error;
    }

    return 'Realtime connection error';
  }

  private bumpEventsCounter(): void {
    this.updateState({
      lastEventAt: new Date().toISOString(),
      eventsReceived: (this.state.eventsReceived ?? 0) + 1,
      lastSyncAt: new Date().toISOString(),
    });
  }

  private resolveAuthHeaders(): Record<string, string> {
    const token = getToken();
    const headers: Record<string, string> = {
      Accept: 'application/json',
    };

    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    return headers;
  }

  private updateDiagnosticsFromEnv(env: ReverbEnv): void {
    this.diagnostics.wsConfigured = Boolean(env.host && env.port);
    this.diagnostics.appKeyPresent = Boolean(env.appKey);
    this.diagnostics.authEndpoint = '/broadcasting/auth';
    this.diagnostics.host = env.host;
    this.diagnostics.port = env.port;
    this.diagnostics.scheme = env.scheme;
    this.diagnostics.forceTLS = env.forceTLS;
    this.diagnostics.lastConnectionStatus = this.state.status;
  }
}

export const realtimeClient = new RealtimeClient();
