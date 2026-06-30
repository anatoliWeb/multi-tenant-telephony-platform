export interface RingGroupPaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  [key: string]: unknown;
}

export type RingGroupStrategy = 'simultaneous' | 'sequential' | 'random' | string;
export type RingGroupStatus = 'active' | 'suspended' | 'archived' | string;
export type RingGroupMemberType = 'extension' | 'user' | string;

export interface RingGroupMemberExtensionOption {
  id: number;
  number: string;
  label?: string | null;
  status?: string | null;
}

export interface RingGroupMemberUserOption {
  id: number;
  name: string;
  email: string;
}

export interface RingGroupMemberItem {
  id: number;
  uuid: string;
  tenant_id: string;
  ring_group_id: number;
  member_type: RingGroupMemberType;
  extension_id?: number | null;
  user_id?: number | null;
  priority: number;
  delay_seconds: number;
  timeout_seconds: number;
  is_active: boolean;
  extension?: RingGroupMemberExtensionOption | null;
  user?: RingGroupMemberUserOption | null;
  metadata?: Record<string, unknown> | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface RingGroupItem {
  id: number;
  uuid: string;
  tenant_id: string;
  name: string;
  slug: string;
  description?: string | null;
  strategy: RingGroupStrategy;
  status: RingGroupStatus;
  ring_timeout_seconds: number;
  max_ring_duration_seconds: number;
  failover_destination_type?: string | null;
  failover_destination_id?: number | null;
  settings?: Record<string, unknown> | null;
  metadata?: Record<string, unknown> | null;
  members_count?: number | null;
  active_members_count?: number | null;
  members?: RingGroupMemberItem[];
  created_by?: number | null;
  updated_by?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface RingGroupFilters {
  search: string;
  status: string;
  strategy: string;
  page: number;
  per_page: number;
}

export interface RingGroupUpsertPayload {
  name: string;
  slug?: string | null;
  description?: string | null;
  strategy: RingGroupStrategy;
  status: RingGroupStatus;
  ring_timeout_seconds: number;
  max_ring_duration_seconds: number;
  failover_destination_type?: 'extension' | 'user' | null;
  failover_destination_id?: number | null;
}

export interface RingGroupMemberUpsertPayload {
  member_type: RingGroupMemberType;
  extension_id?: number | null;
  user_id?: number | null;
  priority: number;
  delay_seconds: number;
  timeout_seconds: number;
  is_active: boolean;
}

export interface RingGroupAssignmentOptions {
  extensions: RingGroupMemberExtensionOption[];
  users: RingGroupMemberUserOption[];
  strategies: RingGroupStrategy[];
  statuses: RingGroupStatus[];
}

export interface RingGroupRoutePlanMember {
  id: number;
  uuid: string;
  member_type: RingGroupMemberType;
  priority: number;
  delay_seconds: number;
  timeout_seconds: number;
  is_active: boolean;
  extension?: RingGroupMemberExtensionOption | null;
  user?: RingGroupMemberUserOption | null;
}

export interface RingGroupRoutePlan {
  ring_group: {
    id: number;
    uuid: string;
    name: string;
    strategy: RingGroupStrategy;
    status: RingGroupStatus;
  };
  resolved_at: string;
  active_member_count: number;
  members: RingGroupRoutePlanMember[];
  failover: {
    type?: string | null;
    id?: number | null;
  };
}
