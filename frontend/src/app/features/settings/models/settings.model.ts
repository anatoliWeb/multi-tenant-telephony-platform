export type SettingScopeSource = 'global' | 'role' | 'permission' | 'user' | 'missing';

export interface SettingScopeRef {
  id: number;
  name: string;
}

export interface SettingScope {
  type: SettingScopeSource;
  user_id: number | null;
  role_id: number | null;
  permission_id: number | null;
  user: SettingScopeRef | null;
  role: SettingScopeRef | null;
  permission: SettingScopeRef | null;
}

export interface SettingItem {
  id: number;
  key: string;
  label: string;
  group: string;
  description: string | null;
  translation_key: string;
  type: string;
  value: unknown;
  default_value: unknown;
  is_frontend: boolean;
  is_backend: boolean;
  is_public: boolean;
  is_encrypted: boolean;
  priority: number;
  inheritance_source: string | null;
  is_active: boolean;
  is_system: boolean;
  scope: SettingScope;
  created_at: string | null;
  updated_at: string | null;
}

export interface SettingEffectiveValue {
  value: unknown;
  raw_value: string | null;
  type: string;
  source: SettingScopeSource;
  setting_id: number | null;
  priority: number | null;
}

export interface SettingsListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface SettingsListPayload {
  settings: SettingItem[];
  effective: Record<string, SettingEffectiveValue>;
  groups: string[];
  types: string[];
  meta: SettingsListMeta;
}

export interface SettingsFilters {
  search: string;
  group: string;
  type: string;
  is_active: '' | 'true' | 'false';
  channel: '' | 'frontend' | 'backend';
  is_public: '' | 'true' | 'false';
  is_encrypted: '' | 'true' | 'false';
  page: number;
  per_page: number;
}

export interface SettingTranslationInput {
  label: string;
  description: string;
}

export interface SettingUpsertPayload {
  key: string;
  label: string;
  group: string;
  description: string;
  type: string;
  value: unknown;
  default_value: unknown;
  is_frontend: boolean;
  is_backend: boolean;
  is_public: boolean;
  is_encrypted: boolean;
  is_active: boolean;
  priority: number;
  translations: Record<string, SettingTranslationInput>;
}
