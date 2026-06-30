import { api } from '../../../services/api/client';
import type { ApiResponse, PaginationMeta } from '../../../types/response.types';
import type {
  SupportCallLog,
  SupportCallLogStatistics,
  SupportContact,
  SupportExtension,
  SupportCallQueue,
  SupportRingGroup,
  SupportListResult,
  SupportPhoneNumber,
} from '../types/tenant-support.types';

const defaultMeta = (): PaginationMeta => ({
  current_page: 1,
  last_page: 1,
  per_page: 0,
  total: 0,
});

const normalizeListResult = <TItem>(response: ApiResponse<TItem[]>): SupportListResult<TItem> => ({
  data: response.data ?? [],
  meta: (response.meta as PaginationMeta | undefined) ?? defaultMeta(),
});

export const tenantSupportService = {
  listContacts: async (): Promise<SupportListResult<SupportContact>> => {
    const response = await api.get<SupportContact[]>('/v1/contacts', { params: { per_page: 20 } });
    return normalizeListResult(response as ApiResponse<SupportContact[]>);
  },

  listExtensions: async (): Promise<SupportListResult<SupportExtension>> => {
    const response = await api.get<SupportExtension[]>('/v1/extensions', { params: { per_page: 20 } });
    return normalizeListResult(response as ApiResponse<SupportExtension[]>);
  },

  listRingGroups: async (): Promise<SupportListResult<SupportRingGroup>> => {
    const response = await api.get<SupportRingGroup[]>('/v1/ring-groups', { params: { per_page: 20 } });
    return normalizeListResult(response as ApiResponse<SupportRingGroup[]>);
  },

  listCallQueues: async (): Promise<SupportListResult<SupportCallQueue>> => {
    const response = await api.get<SupportCallQueue[]>('/v1/call-queues', { params: { per_page: 20 } });
    return normalizeListResult(response as ApiResponse<SupportCallQueue[]>);
  },

  listPhoneNumbers: async (): Promise<SupportListResult<SupportPhoneNumber>> => {
    const response = await api.get<SupportPhoneNumber[]>('/v1/phone-numbers', { params: { per_page: 20 } });
    return normalizeListResult(response as ApiResponse<SupportPhoneNumber[]>);
  },

  listCallLogs: async (): Promise<SupportListResult<SupportCallLog>> => {
    const response = await api.get<SupportCallLog[]>('/v1/call-logs', { params: { per_page: 20 } });
    return normalizeListResult(response as ApiResponse<SupportCallLog[]>);
  },

  getCallLogStatistics: async (): Promise<SupportCallLogStatistics> => {
    const response = await api.get<SupportCallLogStatistics>('/v1/call-logs/statistics');
    return (response as ApiResponse<SupportCallLogStatistics>).data ?? {
      total_calls: 0,
      answered_calls: 0,
      missed_calls: 0,
      answer_rate: 0,
    };
  },

  exportCallLogs: async (): Promise<Blob> => {
    const response = await api.download('/v1/call-logs/export');
    return response.data;
  },
};
