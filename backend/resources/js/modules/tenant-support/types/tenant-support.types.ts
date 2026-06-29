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
