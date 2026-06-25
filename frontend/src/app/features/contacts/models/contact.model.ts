export interface ContactPaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  [key: string]: unknown;
}

export interface ContactPhone {
  id: number;
  uuid: string;
  label?: string | null;
  raw_number: string;
  normalized_number: string;
  extension?: string | null;
  is_primary: boolean;
  is_sms_capable: boolean;
  is_active: boolean;
}

export interface ContactEmail {
  id: number;
  uuid: string;
  label?: string | null;
  email: string;
  normalized_email: string;
  is_primary: boolean;
  is_active: boolean;
}

export interface ContactTag {
  id: number;
  uuid: string;
  tenant_id: string;
  name: string;
  slug: string;
  color?: string | null;
}

export interface ContactItem {
  id: number;
  uuid: string;
  tenant_id: string;
  first_name?: string | null;
  last_name?: string | null;
  display_name: string;
  company_name?: string | null;
  job_title?: string | null;
  notes?: string | null;
  status: 'active' | 'archived' | 'blocked' | string;
  created_by?: number | null;
  updated_by?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
  phones?: ContactPhone[];
  emails?: ContactEmail[];
  tags?: ContactTag[];
  primary_phone?: ContactPhone | null;
  primary_email?: ContactEmail | null;
}

export interface ContactFilters {
  search: string;
  status: string;
  tag: string;
  page: number;
  per_page: number;
}

export interface ContactPhoneDraft {
  label: string;
  raw_number: string;
  extension: string;
  is_primary: boolean;
  is_sms_capable: boolean;
  is_active: boolean;
}

export interface ContactEmailDraft {
  label: string;
  email: string;
  is_primary: boolean;
  is_active: boolean;
}

export interface ContactUpsertPayload {
  first_name?: string | null;
  last_name?: string | null;
  display_name?: string | null;
  company_name?: string | null;
  job_title?: string | null;
  notes?: string | null;
  status: 'active' | 'archived' | 'blocked' | string;
  phones: Array<{
    label?: string | null;
    raw_number: string;
    extension?: string | null;
    is_primary: boolean;
    is_sms_capable: boolean;
    is_active: boolean;
  }>;
  emails: Array<{
    label?: string | null;
    email: string;
    is_primary: boolean;
    is_active: boolean;
  }>;
  tag_ids: number[];
}
