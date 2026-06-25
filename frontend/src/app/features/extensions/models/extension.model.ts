export interface ExtensionPaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  [key: string]: unknown;
}

export interface ExtensionAssignmentUser {
  id: number;
  name: string;
  email: string;
}

export interface ExtensionAssignmentContact {
  id: number;
  display_name: string;
  company_name?: string | null;
}

export interface ExtensionCredentialSummary {
  username: string;
  secret_hint?: string | null;
  version: number;
  rotated_at?: string | null;
}

export interface ExtensionProviderState {
  provider?: string | null;
  endpoint_status?: string | null;
  registration_status?: string | null;
  address?: string | null;
  updated_at?: string | null;
}

export interface ExtensionItem {
  id: number;
  uuid: string;
  tenant_id: string;
  number: string;
  label?: string | null;
  status: 'active' | 'suspended' | 'archived' | string;
  provisioning_status: string;
  registration_status: string;
  provider_name?: string | null;
  provider_resource_id?: string | null;
  credential_username?: string | null;
  credential?: ExtensionCredentialSummary | null;
  assigned_user?: ExtensionAssignmentUser | null;
  assigned_contact?: ExtensionAssignmentContact | null;
  provider_state?: ExtensionProviderState | null;
  last_provisioned_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  plain_secret?: string | null;
}

export interface ExtensionFilters {
  search: string;
  status: string;
  assigned: string;
  page: number;
  per_page: number;
}

export interface ExtensionAssignmentOptions {
  users: ExtensionAssignmentUser[];
  contacts: ExtensionAssignmentContact[];
}

export interface ExtensionUpsertPayload {
  number: string;
  label?: string | null;
  status: 'active' | 'suspended' | 'archived' | string;
  assigned_user_id?: number | null;
  assigned_contact_id?: number | null;
}
