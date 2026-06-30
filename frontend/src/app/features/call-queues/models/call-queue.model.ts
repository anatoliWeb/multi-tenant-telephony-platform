export interface CallQueuePaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  [key: string]: unknown;
}

export type CallQueueStrategy = 'ring_all' | 'round_robin' | 'least_recent' | 'fewest_calls' | 'random' | 'sequential' | string;
export type CallQueueStatus = 'active' | 'suspended' | 'archived' | string;
export type CallQueueMemberType = 'extension' | 'user' | string;
export type CallQueueOverflowType = 'extension' | 'user' | 'ring_group' | 'queue' | string;

export interface CallQueueExtensionOption {
  id: number;
  number: string;
  label?: string | null;
  status?: string | null;
}

export interface CallQueueUserOption {
  id: number;
  name: string;
  email: string;
}

export interface CallQueueOptionDestination {
  id: number;
  label: string;
  sub_label?: string | null;
}

export interface CallQueueMemberItem {
  id: number;
  uuid: string;
  tenant_id: string;
  call_queue_id: number;
  member_type: CallQueueMemberType;
  member_id: number;
  extension_id?: number | null;
  user_id?: number | null;
  priority: number;
  penalty: number;
  is_active: boolean;
  is_paused: boolean;
  paused_at?: string | null;
  pause_reason?: string | null;
  last_call_at?: string | null;
  extension?: CallQueueExtensionOption | null;
  user?: CallQueueUserOption | null;
  metadata?: Record<string, unknown> | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface CallQueueItem {
  id: number;
  uuid: string;
  tenant_id: string;
  name: string;
  slug: string;
  description?: string | null;
  strategy: CallQueueStrategy;
  status: CallQueueStatus;
  max_wait_time_seconds: number;
  ring_timeout_seconds: number;
  retry_delay_seconds: number;
  max_attempts: number;
  music_on_hold?: string | null;
  announce_position: boolean;
  announce_estimated_wait: boolean;
  overflow_destination_type?: CallQueueOverflowType | null;
  overflow_destination_id?: number | null;
  overflow_destination_summary?: string | null;
  settings?: Record<string, unknown> | null;
  metadata?: Record<string, unknown> | null;
  members_count?: number | null;
  active_members_count?: number | null;
  paused_members_count?: number | null;
  members?: CallQueueMemberItem[];
  created_by?: number | null;
  updated_by?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface CallQueueFilters {
  search: string;
  status: string;
  strategy: string;
  page: number;
  per_page: number;
}

export interface CallQueueUpsertPayload {
  name: string;
  slug?: string | null;
  description?: string | null;
  strategy: CallQueueStrategy;
  status: CallQueueStatus;
  max_wait_time_seconds: number;
  ring_timeout_seconds: number;
  retry_delay_seconds: number;
  max_attempts: number;
  music_on_hold?: string | null;
  announce_position?: boolean;
  announce_estimated_wait?: boolean;
  overflow_destination_type?: CallQueueOverflowType | null;
  overflow_destination_id?: number | null;
}

export interface CallQueueMemberUpsertPayload {
  member_type: CallQueueMemberType;
  extension_id?: number | null;
  user_id?: number | null;
  priority: number;
  penalty: number;
  is_active: boolean;
}

export interface CallQueueAssignmentOptions {
  extensions: CallQueueExtensionOption[];
  users: CallQueueUserOption[];
  queues: Array<{ id: number; name: string; slug: string; status: string }>;
  ring_groups: Array<{ id: number; name: string; slug: string; status: string }>;
  strategies: CallQueueStrategy[];
  statuses: CallQueueStatus[];
  overflow_destinations: Array<{
    id: string;
    label: string;
    items: CallQueueOptionDestination[];
  }>;
}

export interface CallQueueRoutePlanMember {
  id: number;
  uuid: string;
  member_type: CallQueueMemberType;
  member_id: number;
  extension_id?: number | null;
  user_id?: number | null;
  priority: number;
  penalty: number;
  is_active: boolean;
  is_paused: boolean;
  extension?: CallQueueExtensionOption | null;
  user?: CallQueueUserOption | null;
}

export interface CallQueueRoutePlan {
  queue: {
    id: number;
    uuid: string;
    name: string;
    strategy: CallQueueStrategy;
    status: CallQueueStatus;
  };
  resolved_at: string;
  eligible_member_count: number;
  members: CallQueueRoutePlanMember[];
  primary_member: CallQueueRoutePlanMember | null;
  overflow: {
    type?: string | null;
    id?: number | null;
    summary?: string | null;
  } | null;
  notes: string[];
}
