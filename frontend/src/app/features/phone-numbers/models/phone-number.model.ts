export interface PhoneNumberPaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  [key: string]: unknown;
}

export interface PhoneNumberExtensionSummary {
  id: number;
  number: string;
  label?: string | null;
}

export interface PhoneNumberAssignedUser {
  id: number;
  name: string;
  email: string;
  extension?: PhoneNumberExtensionSummary | null;
}

export interface PhoneNumberItem {
  id: number;
  uuid: string;
  number: string;
  normalized_number: string;
  display_number: string;
  type: string;
  status: string;
  assignment_status: string;
  is_primary: boolean;
  provider_name?: string | null;
  provider_reference?: string | null;
  country_code?: string | null;
  capabilities?: string[] | null;
  assigned_user?: PhoneNumberAssignedUser | null;
  activated_at?: string | null;
  purchased_at?: string | null;
  released_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface PhoneNumberFilters {
  search: string;
  status: string;
  assigned: string;
  primary: string;
  page: number;
  per_page: number;
}

export interface PhoneNumberAssignmentOptions {
  users: PhoneNumberAssignedUser[];
}

export interface PhoneNumberUpsertPayload {
  number: string;
  display_number?: string | null;
  type?: string | null;
  status: string;
  assigned_user_id?: number | null;
  is_primary?: boolean;
  provider_name?: string | null;
}
