import { Inject, Injectable, OnDestroy } from '@angular/core';
import { BehaviorSubject, Subject } from 'rxjs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { APP_CONFIG, AppEnvironment } from '../../core/tokens/app-config.token';
import { AuthTokenStorageService } from '../../auth/services/auth-token-storage.service';
import { AuthStateService } from '../../core/services/auth-state.service';

export interface RealtimeStatus {
  connected: boolean;
  provider: string;
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
  avatar?: string | null;
  role?: string;
  device_type?: string;
}

export interface ChatTypingPayload {
  conversation_id: number;
  user_id: number;
  name: string;
  device_type?: string;
  started_at?: string | null;
  stopped_at?: string | null;
}

export interface ChatRealtimeMessagePayload {
  id?: number;
  conversation_id?: number;
  message_id?: number;
  sender_id?: number | null;
  sender_type?: string;
  type?: string;
  body?: string | null;
  status?: string;
  sent_at?: string | null;
  edited_at?: string | null;
  deleted_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  [key: string]: unknown;
}

export interface ChatRealtimeMessageReadPayload {
  conversation_id?: number;
  message_id?: number;
  id?: number;
  user_id?: number;
  read_at?: string | null;
  read_source?: string;
  status?: string;
  read_count?: number;
  reads_count?: number;
  delivery_status?: string;
  [key: string]: unknown;
}

export interface ChatRealtimeMessageDeliveryPayload {
  conversation_id?: number;
  message_id?: number;
  id?: number;
  recipient_user_id?: number;
  status?: string;
  delivery_status?: string;
  delivered_at?: string | null;
  read_at?: string | null;
  failed_at?: string | null;
  updated_at?: string | null;
  read_count?: number;
  reads_count?: number;
  [key: string]: unknown;
}

@Injectable({ providedIn: 'root' })
export class RealtimeService implements OnDestroy {
  private static readonly CHANNEL = 'system.notifications';
  private static readonly EVENT = '.system.notification';
  private static readonly ACTIVITY_CHANNEL = 'activity.stream';
  private static readonly ACTIVITY_EVENT = '.activity.logged';
  private static readonly USER_NOTIFICATIONS_CHANNEL_PREFIX = 'notifications.user.';
  private static readonly USER_NOTIFICATIONS_EVENT = '.notification.created';
  private static readonly PRESENCE_ONLINE_CHANNEL = 'presence-online';
  private static readonly PRESENCE_DASHBOARD_CHANNEL = 'presence-dashboard';

  private readonly statusSubject = new BehaviorSubject<RealtimeStatus>({
    connected: false,
    provider: 'reverb',
  });
  private readonly eventsSubject = new BehaviorSubject<SystemNotificationPayload[]>([]);
  private readonly activityEventsSubject = new BehaviorSubject<ActivityStreamPayload[]>([]);
  private readonly notificationCreatedSubject = new BehaviorSubject<NotificationCreatedPayload[]>([]);
  private readonly onlineUsersSubject = new BehaviorSubject<RealtimePresenceUser[]>([]);
  private readonly dashboardPresenceSubject = new BehaviorSubject<RealtimePresenceUser[]>([]);
  private readonly dynamicPresenceSubjects = new Map<string, BehaviorSubject<RealtimePresenceUser[]>>();
  private readonly chatTypingSubjects = new Map<number, BehaviorSubject<ChatTypingPayload[]>>();
  private readonly chatMessageCreatedSubjects = new Map<number, Subject<ChatRealtimeMessagePayload>>();
  private readonly chatMessageUpdatedSubjects = new Map<number, Subject<ChatRealtimeMessagePayload>>();
  private readonly chatMessageDeletedSubjects = new Map<number, Subject<ChatRealtimeMessagePayload>>();
  private readonly chatMessageReadSubjects = new Map<number, Subject<ChatRealtimeMessageReadPayload>>();
  private readonly chatMessageDeviceReadSubjects = new Map<number, Subject<ChatRealtimeMessageReadPayload>>();
  private readonly chatMessageDeliveryUpdatedSubjects = new Map<number, Subject<ChatRealtimeMessageDeliveryPayload>>();
  private readonly joinedChatMessageChannels = new Set<number>();
  private readonly joinedChatTypingChannels = new Set<number>();
  private readonly joinedPresenceChannels = new Set<string>();
  private echo: Echo<'reverb'> | null = null;
  private isConnected = false;
  private isDisconnecting = false;

  readonly status$ = this.statusSubject.asObservable();
  readonly events$ = this.eventsSubject.asObservable();
  readonly activityEvents$ = this.activityEventsSubject.asObservable();
  readonly notificationCreated$ = this.notificationCreatedSubject.asObservable();
  readonly onlineUsers$ = this.onlineUsersSubject.asObservable();
  readonly dashboardPresence$ = this.dashboardPresenceSubject.asObservable();

  constructor(
    @Inject(APP_CONFIG) private readonly config: AppEnvironment,
    private readonly tokenStorage: AuthTokenStorageService,
    private readonly authState: AuthStateService,
  ) {}

  connect(): void {
    if (!this.config.realtime.enabled || this.echo) {
      return;
    }

    if (!this.config.realtime.appKey) {
      console.warn('[Realtime] skipped: missing realtime.appKey');
      return;
    }

    // Reverb uses the Pusher protocol; Echo delegates reconnection to pusher-js.
    (window as Window & { Pusher?: typeof Pusher }).Pusher = Pusher;
    const wsHost = this.config.realtime.wsHost || window.location.hostname;
    this.echo = new Echo({
      broadcaster: 'reverb',
      key: this.config.realtime.appKey,
      wsHost,
      wsPort: this.config.realtime.wsPort,
      wssPort: this.config.realtime.wsPort,
      forceTLS: this.config.realtime.forceTLS,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: this.resolveBroadcastingAuthEndpoint(),
      withCredentials: true,
      auth: {
        headers: this.resolveAuthHeaders(),
      },
    });

    const connector = this.echo.connector.pusher.connection;
    connector.bind('connected', () => {
      console.info('[Realtime] connected');
      this.updateConnectionState(true);
    });
    connector.bind('disconnected', () => {
      console.warn('[Realtime] disconnected');
      this.updateConnectionState(false);
    });
    connector.bind('unavailable', () => {
      console.warn('[Realtime] unavailable');
      this.updateConnectionState(false);
    });
    connector.bind('failed', () => {
      console.error('[Realtime] failed');
      this.updateConnectionState(false);
    });
    connector.bind('error', (error: unknown) => {
      console.error('[Realtime] error', error);
    });
    connector.bind('state_change', (states: { previous: string; current: string }) => {
      console.info('[Realtime] state', states.previous, '->', states.current);
    });

    const notificationChannel = this.config.realtime.usePrivateChannel
      ? this.echo.private(RealtimeService.CHANNEL)
      : this.echo.channel(RealtimeService.CHANNEL);

    notificationChannel
      .subscribed(() => {
        console.info('[Realtime] subscribed to', RealtimeService.CHANNEL);
      })
      .listen(RealtimeService.EVENT, (payload: SystemNotificationPayload) => {
        console.info('[Realtime] event received', payload);
        const nextEvents = [payload, ...this.eventsSubject.value].slice(0, 20);
        this.eventsSubject.next(nextEvents);
      });

    this.echo
      .private(RealtimeService.ACTIVITY_CHANNEL)
      .subscribed(() => {
        console.info('[Realtime] subscribed to', RealtimeService.ACTIVITY_CHANNEL);
      })
      .listen(RealtimeService.ACTIVITY_EVENT, (payload: ActivityStreamPayload) => {
        const nextActivityEvents = [payload, ...this.activityEventsSubject.value].slice(0, 20);
        this.activityEventsSubject.next(nextActivityEvents);
      });

    const currentUserId = this.authState.userId;
    if (currentUserId) {
      const userNotificationsChannel = `${RealtimeService.USER_NOTIFICATIONS_CHANNEL_PREFIX}${currentUserId}`;
      this.echo
        .private(userNotificationsChannel)
        .listen(RealtimeService.USER_NOTIFICATIONS_EVENT, (payload: NotificationCreatedPayload) => {
          const nextNotificationEvents = [payload, ...this.notificationCreatedSubject.value].slice(0, 20);
          this.notificationCreatedSubject.next(nextNotificationEvents);
        });
    }

    this.joinPresence(RealtimeService.PRESENCE_ONLINE_CHANNEL);
    this.joinPresence(RealtimeService.PRESENCE_DASHBOARD_CHANNEL);
  }

  reconnect(): void {
    this.disconnect();
    this.connect();
  }

  disconnect(): void {
    if (!this.echo || this.isDisconnecting) {
      return;
    }

    this.isDisconnecting = true;
    try {
      this.echo.leave(`private-${RealtimeService.CHANNEL}`);
      this.echo.leave(`private-${RealtimeService.ACTIVITY_CHANNEL}`);
      const currentUserId = this.authState.userId;
      if (currentUserId) {
        this.echo.leave(`private-${RealtimeService.USER_NOTIFICATIONS_CHANNEL_PREFIX}${currentUserId}`);
      }
      this.echo.leave(`presence-${RealtimeService.PRESENCE_ONLINE_CHANNEL}`);
      this.echo.leave(`presence-${RealtimeService.PRESENCE_DASHBOARD_CHANNEL}`);
      this.joinedPresenceChannels.clear();
      const connection = this.echo.connector.pusher.connection;
      if (connection.state !== 'disconnected' && connection.state !== 'disconnecting') {
        this.echo.disconnect();
      }
    } catch (error) {
      if (!this.config.production) {
        console.warn('[Realtime] safe disconnect warning', error);
      }
    } finally {
      this.echo = null;
      this.isDisconnecting = false;
      this.updateConnectionState(false);
    }
  }

  clearEvents(): void {
    this.eventsSubject.next([]);
    this.activityEventsSubject.next([]);
    this.notificationCreatedSubject.next([]);
    this.onlineUsersSubject.next([]);
    this.dashboardPresenceSubject.next([]);
  }

  ngOnDestroy(): void {
    this.disconnect();
  }

  joinPresence(channelName: string): void {
    if (!this.echo || this.joinedPresenceChannels.has(channelName)) {
      return;
    }

    const target = this.resolvePresenceSubject(channelName);
    this.echo.join(channelName)
      .here((users: RealtimePresenceUser[]) => {
        target.next(users);
      })
      .joining((user: RealtimePresenceUser) => {
        if (target.value.some((item) => item.id === user.id)) {
          return;
        }

        target.next([...target.value, user]);
      })
      .leaving((user: RealtimePresenceUser) => {
        target.next(target.value.filter((item) => item.id !== user.id));
      });

    this.joinedPresenceChannels.add(channelName);
  }

  leavePresence(channelName: string): void {
    if (!this.echo) {
      return;
    }

    this.echo.leave(`presence-${channelName}`);
    this.resolvePresenceSubject(channelName).next([]);
    this.joinedPresenceChannels.delete(channelName);
  }

  observePresence(channelName: string) {
    return this.resolvePresenceSubject(channelName).asObservable();
  }

  observeChatTyping(conversationId: number) {
    return this.resolveChatTypingSubject(conversationId).asObservable();
  }

  observeChatMessageCreated(conversationId: number) {
    return this.resolveChatMessageCreatedSubject(conversationId).asObservable();
  }

  observeChatMessageUpdated(conversationId: number) {
    return this.resolveChatMessageUpdatedSubject(conversationId).asObservable();
  }

  observeChatMessageDeleted(conversationId: number) {
    return this.resolveChatMessageDeletedSubject(conversationId).asObservable();
  }

  observeChatMessageRead(conversationId: number) {
    return this.resolveChatMessageReadSubject(conversationId).asObservable();
  }

  observeChatMessageDeviceRead(conversationId: number) {
    return this.resolveChatMessageDeviceReadSubject(conversationId).asObservable();
  }

  observeChatMessageDeliveryUpdated(conversationId: number) {
    return this.resolveChatMessageDeliveryUpdatedSubject(conversationId).asObservable();
  }

  joinChatMessages(conversationId: number): void {
    if (!this.echo || this.joinedChatMessageChannels.has(conversationId)) {
      return;
    }

    const created = this.resolveChatMessageCreatedSubject(conversationId);
    const updated = this.resolveChatMessageUpdatedSubject(conversationId);
    const deleted = this.resolveChatMessageDeletedSubject(conversationId);
    const read = this.resolveChatMessageReadSubject(conversationId);
    const deviceRead = this.resolveChatMessageDeviceReadSubject(conversationId);
    const deliveryUpdated = this.resolveChatMessageDeliveryUpdatedSubject(conversationId);
    const channel = this.echo.private(`chat.conversation.${conversationId}`);

    channel.listen('.chat.message.created', (payload: ChatRealtimeMessagePayload) => {
      created.next(payload);
    });
    channel.listen('.chat.message.updated', (payload: ChatRealtimeMessagePayload) => {
      updated.next(payload);
    });
    channel.listen('.chat.message.deleted', (payload: ChatRealtimeMessagePayload) => {
      deleted.next(payload);
    });
    channel.listen('.chat.message.read', (payload: ChatRealtimeMessageReadPayload) => {
      read.next(payload);
    });
    channel.listen('.chat.message.device_read', (payload: ChatRealtimeMessageReadPayload) => {
      deviceRead.next(payload);
    });
    channel.listen('.chat.message.delivery.updated', (payload: ChatRealtimeMessageDeliveryPayload) => {
      deliveryUpdated.next(payload);
    });

    this.joinedChatMessageChannels.add(conversationId);
  }

  leaveChatMessages(conversationId: number): void {
    if (!this.echo) {
      return;
    }

    this.echo.leave(`private-chat.conversation.${conversationId}`);
    this.joinedChatMessageChannels.delete(conversationId);
  }

  joinChatTyping(conversationId: number): void {
    if (!this.echo || this.joinedChatTypingChannels.has(conversationId)) {
      return;
    }

    const subject = this.resolveChatTypingSubject(conversationId);
    const channel = this.echo.private(`chat.conversation.${conversationId}`);
    channel.listen('.chat.typing.started', (payload: ChatTypingPayload) => {
      const next = [payload, ...subject.value.filter((item) => item.user_id !== payload.user_id)];
      subject.next(next);
    });
    channel.listen('.chat.typing.stopped', (payload: ChatTypingPayload) => {
      subject.next(subject.value.filter((item) => item.user_id !== payload.user_id));
    });

    this.joinedChatTypingChannels.add(conversationId);
  }

  leaveChatTyping(conversationId: number): void {
    if (!this.echo) {
      return;
    }

    this.echo.leave(`private-chat.conversation.${conversationId}`);
    this.resolveChatTypingSubject(conversationId).next([]);
    this.joinedChatTypingChannels.delete(conversationId);
  }

  private updateConnectionState(connected: boolean): void {
    if (this.isConnected === connected) {
      return;
    }

    this.isConnected = connected;
    this.statusSubject.next({
      connected,
      provider: this.statusSubject.value.provider,
    });
  }

  private resolveAuthHeaders(): Record<string, string> {
    const token = this.tokenStorage.getToken();
    const headers: Record<string, string> = {
      Accept: 'application/json',
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    return headers;
  }

  private resolveBroadcastingAuthEndpoint(): string {
    const configured = this.config.realtime.broadcastingAuthUrl?.trim();
    if (configured) {
      return configured;
    }

    return '/broadcasting/auth';
  }

  private resolvePresenceSubject(channelName: string): BehaviorSubject<RealtimePresenceUser[]> {
    if (channelName === RealtimeService.PRESENCE_ONLINE_CHANNEL) {
      return this.onlineUsersSubject;
    }

    if (channelName === RealtimeService.PRESENCE_DASHBOARD_CHANNEL) {
      return this.dashboardPresenceSubject;
    }

    if (!this.dynamicPresenceSubjects.has(channelName)) {
      this.dynamicPresenceSubjects.set(channelName, new BehaviorSubject<RealtimePresenceUser[]>([]));
    }

    return this.dynamicPresenceSubjects.get(channelName) as BehaviorSubject<RealtimePresenceUser[]>;
  }

  private resolveChatTypingSubject(conversationId: number): BehaviorSubject<ChatTypingPayload[]> {
    if (!this.chatTypingSubjects.has(conversationId)) {
      this.chatTypingSubjects.set(conversationId, new BehaviorSubject<ChatTypingPayload[]>([]));
    }

    return this.chatTypingSubjects.get(conversationId) as BehaviorSubject<ChatTypingPayload[]>;
  }

  private resolveChatMessageCreatedSubject(conversationId: number): Subject<ChatRealtimeMessagePayload> {
    if (!this.chatMessageCreatedSubjects.has(conversationId)) {
      this.chatMessageCreatedSubjects.set(conversationId, new Subject<ChatRealtimeMessagePayload>());
    }

    return this.chatMessageCreatedSubjects.get(conversationId) as Subject<ChatRealtimeMessagePayload>;
  }

  private resolveChatMessageUpdatedSubject(conversationId: number): Subject<ChatRealtimeMessagePayload> {
    if (!this.chatMessageUpdatedSubjects.has(conversationId)) {
      this.chatMessageUpdatedSubjects.set(conversationId, new Subject<ChatRealtimeMessagePayload>());
    }

    return this.chatMessageUpdatedSubjects.get(conversationId) as Subject<ChatRealtimeMessagePayload>;
  }

  private resolveChatMessageDeletedSubject(conversationId: number): Subject<ChatRealtimeMessagePayload> {
    if (!this.chatMessageDeletedSubjects.has(conversationId)) {
      this.chatMessageDeletedSubjects.set(conversationId, new Subject<ChatRealtimeMessagePayload>());
    }

    return this.chatMessageDeletedSubjects.get(conversationId) as Subject<ChatRealtimeMessagePayload>;
  }

  private resolveChatMessageReadSubject(conversationId: number): Subject<ChatRealtimeMessageReadPayload> {
    if (!this.chatMessageReadSubjects.has(conversationId)) {
      this.chatMessageReadSubjects.set(conversationId, new Subject<ChatRealtimeMessageReadPayload>());
    }

    return this.chatMessageReadSubjects.get(conversationId) as Subject<ChatRealtimeMessageReadPayload>;
  }

  private resolveChatMessageDeviceReadSubject(conversationId: number): Subject<ChatRealtimeMessageReadPayload> {
    if (!this.chatMessageDeviceReadSubjects.has(conversationId)) {
      this.chatMessageDeviceReadSubjects.set(conversationId, new Subject<ChatRealtimeMessageReadPayload>());
    }

    return this.chatMessageDeviceReadSubjects.get(conversationId) as Subject<ChatRealtimeMessageReadPayload>;
  }

  private resolveChatMessageDeliveryUpdatedSubject(conversationId: number): Subject<ChatRealtimeMessageDeliveryPayload> {
    if (!this.chatMessageDeliveryUpdatedSubjects.has(conversationId)) {
      this.chatMessageDeliveryUpdatedSubjects.set(conversationId, new Subject<ChatRealtimeMessageDeliveryPayload>());
    }

    return this.chatMessageDeliveryUpdatedSubjects.get(conversationId) as Subject<ChatRealtimeMessageDeliveryPayload>;
  }
}
