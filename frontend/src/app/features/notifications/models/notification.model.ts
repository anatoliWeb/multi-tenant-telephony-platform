export interface NotificationPreview {
  id: string;
  type: 'info' | 'success' | 'warning' | 'error';
  title: string | null;
  message: string | null;
  createdAt: string | null;
  read: boolean;
}

export interface NotificationPreferences {
  'system.enabled': boolean;
  'realtime.enabled': boolean;
  'email.enabled': boolean;
  'activity.enabled': boolean;
}
