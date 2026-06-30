import type { PaginationMeta } from '../../../types/response.types';

export interface SupportListResult<TItem> {
  data: TItem[];
  meta: PaginationMeta;
}

export interface SupportTenantOption {
  id: string;
  uuid: string;
  name: string;
  slug: string;
  status: string;
}

export interface SupportContact {
  id: number;
  uuid: string;
  display_name: string;
  company_name: string | null;
  status: string;
  primary_phone?: { raw_number?: string | null; normalized_number?: string | null } | null;
  primary_email?: { email?: string | null } | null;
}

export interface SupportExtension {
  id: number;
  uuid: string;
  number: string;
  label: string | null;
  status: string;
  assigned_user?: { id: number; name: string; email: string } | null;
  assigned_contact?: { id: number; display_name: string; company_name: string | null } | null;
  provider_state?: { endpoint_status?: string | null; registration_status?: string | null } | null;
}

export interface SupportRingGroupMember {
  id: number;
  uuid: string;
  member_type: string;
  priority: number;
  delay_seconds: number;
  timeout_seconds: number;
  is_active: boolean;
  extension?: { id: number; number: string; label: string | null } | null;
  user?: { id: number; name: string; email: string } | null;
}

export interface SupportRingGroup {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  description: string | null;
  strategy: string;
  status: string;
  ring_timeout_seconds: number;
  max_ring_duration_seconds: number;
  members_count?: number | null;
  active_members_count?: number | null;
  members?: SupportRingGroupMember[];
}

export interface SupportCallQueue {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  strategy: string;
  status: string;
  members_count?: number | null;
  active_members_count?: number | null;
  paused_members_count?: number | null;
  overflow_destination_summary?: string | null;
}

export interface SupportPhoneNumber {
  id: number;
  uuid: string;
  display_number: string | null;
  number: string;
  status: string;
  assignment_status: string;
  is_primary: boolean;
  assigned_user?: {
    id: number;
    name: string;
    email: string;
    extension?: { id: number; number: string; label: string | null } | null;
  } | null;
}

export interface SupportCallLog {
  id: number;
  uuid: string;
  provider_call_id: string | null;
  direction: string;
  status: string;
  disposition: string | null;
  from_number: string | null;
  to_number: string | null;
  started_at: string | null;
  total_seconds: number;
  caller?: {
    user?: { id: number; name: string; email: string } | null;
    extension?: { id: number; number: string; label: string | null } | null;
    contact?: { id: number; display_name: string } | null;
  } | null;
  callee?: {
    user?: { id: number; name: string; email: string } | null;
    extension?: { id: number; number: string; label: string | null } | null;
    contact?: { id: number; display_name: string } | null;
  } | null;
}

export interface SupportCallLogStatistics {
  total_calls: number;
  answered_calls: number;
  missed_calls: number;
  answer_rate: number;
}
