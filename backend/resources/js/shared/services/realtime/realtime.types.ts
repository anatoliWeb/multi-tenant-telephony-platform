export interface RealtimeStatusMetric {
  key: 'backend_online' | 'frontend_online' | 'presence_online' | 'presence_dashboard';
  label: string;
  count: number;
  active: boolean;
}

export interface RealtimeConnectionState {
  connected: boolean;
  transport: 'websocket' | 'polling' | 'none';
  status?: 'disconnected' | 'connecting' | 'connected' | 'error';
  lastSyncAt?: string;
  connectedAt?: string;
  lastEventAt?: string;
  eventsReceived?: number;
  lastError?: string;
}

export interface RealtimeDiagnosticsState {
  wsConfigured: boolean;
  appKeyPresent: boolean;
  authEndpoint: string;
  host: string;
  port: number;
  scheme: 'http' | 'https';
  forceTLS: boolean;
  lastConnectionStatus: RealtimeConnectionState['status'];
  lastConnectionError?: string;
  lastAuthStatus?: 'idle' | 'ok' | 'error';
  lastPresenceError?: string;
  lastJoinedChannels: string[];
}

export interface SystemNotificationPayload {
  type: string;
  title: string;
  message: string;
  created_at: string;
}

export interface ActivityStreamPayload {
  id: number;
  action: string;
  description: string | null;
  user: {
    id: number;
    name: string;
  } | null;
  created_at: string | null;
  meta?: {
    source?: string;
    module?: string;
  };
}

export interface NotificationCreatedPayload {
  id: string;
  type: string;
  title: string | null;
  message: string | null;
  is_read: boolean;
  read_at: string | null;
  created_at: string | null;
}

export interface RealtimePresenceUser {
  id: number;
  name: string;
}

export interface RealtimePresenceState {
  users: RealtimePresenceUser[];
  count: number;
}
