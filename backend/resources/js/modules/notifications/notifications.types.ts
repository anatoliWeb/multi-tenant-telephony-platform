export type NotificationStatusFilter = 'all' | 'read' | 'unread';

export interface NotificationListQuery {
  status?: NotificationStatusFilter;
  limit?: number;
}

export interface NotificationItem {
  id: string;
  type: string;
  title: string | null;
  message: string | null;
  is_read: boolean;
  read_at: string | null;
  created_at: string | null;
}

export interface NotificationPreferences {
  'system.enabled': boolean;
  'realtime.enabled': boolean;
  'email.enabled': boolean;
  'activity.enabled': boolean;
}
