export interface TenantSummary {
  id: string;
  uuid: string;
  name: string;
  slug: string;
  status: string;
  timezone: string;
  locale: string;
  currency: string;
  settings: Record<string, unknown>;
  activated_at: string | null;
  suspended_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface TenantMembershipSummary {
  id: string;
  tenant_id: string;
  user_id: number;
  status: string;
  invited_by: number | null;
  invited_at: string | null;
  accepted_at: string | null;
  activated_at: string | null;
  suspended_at: string | null;
  tenant: TenantSummary | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface TenantMembershipListPayload {
  tenants: TenantMembershipSummary[];
  current_tenant_id: string | null;
}

export interface TenantContextPayload {
  tenant: TenantSummary | null;
  membership: TenantMembershipSummary | null;
  current_tenant_id: string | null;
}
