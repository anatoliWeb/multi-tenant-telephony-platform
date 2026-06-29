export interface CallLogPartySummaryItem {
  id: number;
  name?: string | null;
  email?: string | null;
  number?: string | null;
  label?: string | null;
  display_number?: string | null;
  display_name?: string | null;
}

export interface CallLogPartySummary {
  user: CallLogPartySummaryItem | null;
  extension: CallLogPartySummaryItem | null;
  phone_number: CallLogPartySummaryItem | null;
  contact: CallLogPartySummaryItem | null;
}

export interface CallLogItem {
  id: number;
  uuid: string;
  provider_id: string;
  provider_call_id: string;
  correlation_id?: string | null;
  direction: string;
  status: string;
  disposition?: string | null;
  from_number?: string | null;
  from_normalized_number?: string | null;
  to_number?: string | null;
  to_normalized_number?: string | null;
  caller: CallLogPartySummary;
  callee: CallLogPartySummary;
  started_at?: string | null;
  ringing_at?: string | null;
  answered_at?: string | null;
  ended_at?: string | null;
  ringing_seconds: number;
  talk_seconds: number;
  billable_seconds: number;
  total_seconds: number;
  hangup_cause?: string | null;
  failure_code?: string | null;
  failure_message?: string | null;
  billing_status?: string | null;
  recording_available: boolean;
}

export interface CallEventItem {
  id: number;
  uuid: string;
  provider_event_id: string;
  provider_id: string;
  type: string;
  occurred_at?: string | null;
  sequence?: number | null;
  summary: {
    disposition?: string | null;
    hangup_cause?: string | null;
    failure_code?: string | null;
  };
}

export interface CallLogFilters {
  search: string;
  direction: string;
  status: string;
  disposition: string;
  user: string;
  date_from: string;
  date_to: string;
  page: number;
  per_page: number;
}

export interface CallLogPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface CallLogUserOption {
  id: number;
  name: string;
  email: string;
  extension: {
    id: number;
    number: string;
    label: string;
  } | null;
}

export interface CallLogFilterOptions {
  users: CallLogUserOption[];
}

export interface CallLogStatistics {
  window: {
    date_from: string;
    date_to: string;
  };
  total_calls: number;
  answered_calls: number;
  missed_calls: number;
  failed_calls: number;
  inbound_calls: number;
  outbound_calls: number;
  internal_calls: number;
  total_talk_seconds: number;
  average_talk_seconds: number;
  answer_rate: number;
  calls_by_day: Array<{ day: string; total: number }>;
  calls_by_status: Array<{ status: string; total: number }>;
  calls_by_direction: Array<{ direction: string; total: number }>;
  top_users: Array<{ user_id: number | null; user_name: string | null; total: number }>;
}
