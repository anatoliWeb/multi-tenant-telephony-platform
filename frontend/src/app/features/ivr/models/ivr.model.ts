export interface IvrPaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  [key: string]: unknown;
}

export type IvrMenuStatus = 'active' | 'suspended' | 'archived' | string;
export type IvrActionType = 'repeat' | 'route' | 'hangup' | string;
export type IvrDestinationType = 'extension' | 'ring_group' | 'call_queue' | 'ivr_menu' | 'hangup' | 'voicemail_placeholder' | string;

export interface IvrTargetExtension {
  id: number;
  number: string;
  label?: string | null;
  status?: string | null;
}

export interface IvrTargetGroup {
  id: number;
  name: string;
  slug: string;
  status: string;
}

export interface IvrOptionItem {
  id: number;
  uuid: string;
  tenant_id: string;
  ivr_menu_id: number;
  digit: string;
  label: string;
  destination_type: IvrDestinationType;
  destination_id?: number | null;
  destination_summary?: string | null;
  priority: number;
  is_active: boolean;
  metadata?: Record<string, unknown> | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface IvrMenuItem {
  id: number;
  uuid: string;
  tenant_id: string;
  name: string;
  slug: string;
  description?: string | null;
  status: IvrMenuStatus;
  greeting_text?: string | null;
  greeting_audio_path?: string | null;
  repeat_count: number;
  input_timeout_seconds: number;
  max_invalid_attempts: number;
  timeout_action_type: IvrActionType;
  timeout_destination_type?: IvrDestinationType | null;
  timeout_destination_id?: number | null;
  timeout_destination_summary?: string | null;
  invalid_action_type: IvrActionType;
  invalid_destination_type?: IvrDestinationType | null;
  invalid_destination_id?: number | null;
  invalid_destination_summary?: string | null;
  settings?: Record<string, unknown> | null;
  metadata?: Record<string, unknown> | null;
  options_count?: number | null;
  active_options_count?: number | null;
  options?: IvrOptionItem[];
  created_by?: number | null;
  updated_by?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface IvrFilters {
  search: string;
  status: string;
  page: number;
  per_page: number;
}

export interface IvrMenuUpsertPayload {
  name: string;
  slug?: string | null;
  description?: string | null;
  status: IvrMenuStatus;
  greeting_text?: string | null;
  greeting_audio_path?: string | null;
  repeat_count: number;
  input_timeout_seconds: number;
  max_invalid_attempts: number;
  timeout_action_type: IvrActionType;
  timeout_destination_type?: IvrDestinationType | null;
  timeout_destination_id?: number | null;
  invalid_action_type: IvrActionType;
  invalid_destination_type?: IvrDestinationType | null;
  invalid_destination_id?: number | null;
}

export interface IvrOptionUpsertPayload {
  digit: string;
  label: string;
  destination_type: IvrDestinationType;
  destination_id?: number | null;
  priority: number;
  is_active: boolean;
}

export interface IvrAssignmentOptions {
  extensions: IvrTargetExtension[];
  ring_groups: IvrTargetGroup[];
  call_queues: IvrTargetGroup[];
  ivr_menus: IvrTargetGroup[];
  statuses: IvrMenuStatus[];
  destination_types: IvrDestinationType[];
  actions: IvrActionType[];
  digits: string[];
}

export interface IvrRoutePlan {
  ivr_menu: {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    status: IvrMenuStatus;
  };
  resolved_at: string;
  input_type: string;
  digit: string | null;
  reason: string;
  option: {
    id: number;
    uuid: string;
    digit: string;
    label: string;
  } | null;
  destination: {
    type: string;
    id?: number | null;
    summary?: string | null;
  } | null;
  notes: string[];
}
