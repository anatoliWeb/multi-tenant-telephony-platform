export type ChatConversationType = 'direct' | 'group' | 'support' | 'external' | 'system' | string;
export type ChatConversationVisibility = 'private' | 'public' | string;
export type ChatConversationStatus = 'active' | 'archived' | 'closed' | 'deleted' | string;

export interface ChatAdminParticipant {
  user_id: number;
  name?: string | null;
  role?: string;
  status?: string;
  access_state?: string;
  block_display_mode?: string | null;
  can_send?: boolean;
  can_attach?: boolean;
  can_invite?: boolean;
  can_remove?: boolean;
  can_manage?: boolean;
  can_moderate?: boolean;
  joined_at?: string | null;
  last_read_at?: string | null;
}

export interface ChatAdminConversation {
  id: number;
  uuid?: string;
  type?: ChatConversationType;
  visibility?: ChatConversationVisibility;
  title?: string | null;
  description?: string | null;
  status?: ChatConversationStatus;
  source?: string;
  last_message_at?: string | null;
  unread_count?: number;
  participants_count?: number;
  assigned_to?: number | null;
  assigned_admin_id?: number | null;
  restricted_participants_count?: number | null;
  failed_webhook_deliveries_count?: number | null;
  imported_messages_count?: number | null;
  current_user_access?: {
    access_state?: string;
    can_send?: boolean;
  } | null;
}

export interface ChatAdminMessage {
  id: number;
  uuid?: string;
  conversation_id: number;
  sender_id?: number | null;
  sender_type?: string;
  type?: string;
  body?: string | null;
  status?: string;
  source?: string | null;
  external_provider?: string | null;
  external_message_id?: string | null;
  direction?: string | null;
  external_mapping?: ChatAdminExternalMappingSafe | null;
  is_imported?: boolean;
  imported_from_message_id?: number | null;
  imported_from_conversation_id?: number | null;
  imported_at?: string | null;
  import_mode?: string | null;
  copied_from_message_id?: number | null;
  sent_at?: string | null;
  edited_at?: string | null;
  deleted_at?: string | null;
  created_at?: string | null;
  delivery_status?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  failed_at?: string | null;
  read_source?: string | null;
  read_count?: number | null;
  reads_count?: number | null;
  delivery_count?: number | null;
  deliveries_count?: number | null;
  device_read_count?: number | null;
  message_deliveries?: ChatAdminMessageDeliveryItem[];
  message_reads?: ChatAdminMessageReadItem[];
  device_reads?: ChatAdminMessageDeviceReadItem[];
  attachments?: ChatAdminAttachmentItem[];
}

export interface ChatAdminExternalMappingSafe {
  provider?: string | null;
  external_message_id?: string | null;
  direction?: string | null;
}

export interface ChatAdminAttachmentItem {
  id: number;
  original_name?: string | null;
  mime_type?: string | null;
  size?: number | null;
  status?: string | null;
  is_imported?: boolean;
  created_at?: string | null;
  download_url?: string | null;
}

export interface ChatAdminMessageDeliveryItem {
  user_id?: number;
  recipient_user_id?: number;
  status?: string;
  delivered_at?: string | null;
  read_at?: string | null;
  failed_at?: string | null;
}

export interface ChatAdminMessageReadItem {
  user_id?: number;
  read_at?: string | null;
  read_source?: string | null;
}

export interface ChatAdminMessageDeviceReadItem {
  user_id?: number;
  read_at?: string | null;
  device_type?: string | null;
}

export interface ChatAdminConversationDetails {
  conversation: ChatAdminConversation | null;
  participants_count: number;
  last_message_at: string | null;
}

export interface ChatAdminConversationFilters {
  search: string;
  type: string;
  status: string;
  visibility: string;
  source: string;
  unreadOnly: boolean;
  assignment: 'all' | 'assigned' | 'unassigned';
  participantRestriction: 'all' | 'blocked' | 'restricted';
  failedWebhookDeliveryOnly: boolean;
  importedOnly: boolean;
}

export interface ChatAdminWebhookDeliverySummary {
  id: number;
  event_type?: string | null;
  status?: 'pending' | 'sent' | 'retrying' | 'failed' | 'cancelled' | string | null;
  attempts?: number | null;
  max_attempts?: number | null;
  next_retry_at?: string | null;
  last_status_code?: number | null;
  error_summary?: string | null;
  endpoint_name?: string | null;
  endpoint_url?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  sent_at?: string | null;
  failed_at?: string | null;
}
