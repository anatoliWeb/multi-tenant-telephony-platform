import type { RealtimeStatusMetric } from './realtime.types';

/**
 * Realtime channel contracts prepared for future Reverb/Echo integration.
 *
 * WHY THIS LAYER EXISTS:
 * Centralizing channel names avoids tight coupling between UI widgets and
 * transport details, making it safer to migrate from mock counters to live
 * subscriptions without rewriting topbar components.
 */
export const REALTIME_CHANNELS = {
  backendOnline: 'presence.backend.online',
  frontendOnline: 'presence.frontend.online',
  systemNotificationsPublic: 'system.notifications',
  systemNotificationsPrivate: 'system.notifications',
  activityStreamPrivate: 'activity.stream',
  notificationsUserPrefix: 'notifications.user.',
  presenceOnline: 'presence-online',
  presenceDashboard: 'presence-dashboard',
  presencePagePrefix: 'presence-page.',
  presenceTypingPrefix: 'presence-typing.',
} as const;

export const REALTIME_EVENTS = {
  systemNotification: '.system.notification',
  activityLogged: '.activity.logged',
  notificationCreated: '.notification.created',
} as const;

export const REALTIME_METRIC_KEYS: ReadonlyArray<RealtimeStatusMetric['key']> = [
  'backend_online',
  'frontend_online',
  'presence_online',
  'presence_dashboard',
];
