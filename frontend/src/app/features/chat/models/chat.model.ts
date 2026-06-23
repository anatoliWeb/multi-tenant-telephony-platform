export interface ChatApiMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  [key: string]: unknown;
}

export interface ChatPaginatedResponse<T> {
  data: T[];
  meta?: ChatApiMeta;
}

export interface ChatParticipant {
  id?: number;
  user_id: number;
  name?: string | null;
  user?: {
    id?: number;
    name?: string | null;
    [key: string]: unknown;
  } | null;
  role?: 'owner' | 'admin' | 'member' | 'viewer' | 'support' | string;
  status?: 'active' | 'invited' | 'left' | 'removed' | 'blocked' | string;
  access_state?: 'full' | 'read_only' | 'hidden' | 'blocked' | string;
  block_display_mode?: 'hide_chat' | 'show_notice' | 'show_read_only_history' | string | null;
  joined_at?: string | null;
  created_at?: string | null;
  last_read_at?: string | null;
  can_send?: boolean;
  can_attach?: boolean;
  can_invite?: boolean;
  can_remove?: boolean;
  can_manage?: boolean;
  can_moderate?: boolean;
}

export interface ChatConversation {
  id: number;
  uuid?: string;
  type?: 'direct' | 'group' | 'support' | 'external' | 'system' | string;
  visibility?: 'private' | 'public' | string;
  title?: string | null;
  description?: string | null;
  status?: 'active' | 'archived' | 'closed' | 'deleted' | string;
  source?: 'internal' | 'api' | 'webhook' | 'system' | string;
  last_message_at?: string | null;
  unread_count?: number;
  participants_count?: number;
  current_user_access?: ChatParticipant;
  participants?: ChatParticipant[];
}

export interface ChatAttachment {
  id: number;
  message_id: number;
  conversation_id: number;
  original_name?: string | null;
  mime_type?: string | null;
  size?: number;
  status?: 'active' | 'deleted' | 'quarantined' | 'failed' | string;
  is_imported?: boolean;
  preview_metadata?: Record<string, unknown> | null;
  created_at?: string | null;
}

export interface ChatMessage {
  id: number;
  uuid?: string;
  conversation_id: number;
  sender_id?: number | null;
  sender_type?: 'user' | 'admin' | 'external' | 'system' | string;
  type?: 'text' | 'file' | 'mixed' | 'system' | string;
  body?: string | null;
  status?: 'pending' | 'sent' | 'delivered' | 'read' | 'failed' | 'deleted' | string;
  delivery_status?: string | null;
  read_count?: number | null;
  reads_count?: number | null;
  is_imported?: boolean;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  edited_at?: string | null;
  deleted_at?: string | null;
  created_at?: string | null;
  attachments_count?: number;
  attachments?: ChatAttachment[];
}

export interface ChatDevice {
  device_key: string;
  device_name?: string;
  device_type?: 'browser' | 'mobile' | 'desktop' | 'tablet' | 'api' | 'unknown' | string;
  platform?: string;
  browser?: string;
  app_version?: string;
}

export interface ChatReadState {
  conversation_id: number;
  message_id?: number;
  user_id?: number;
  read_at?: string | null;
  device_type?: string;
}

export interface ChatTypingPayload {
  device_key?: string;
  device_type?: string;
}

export interface ChatPresenceUser {
  id: number;
  name: string;
  avatar?: string | null;
  role?: string;
  device_type?: string;
}
