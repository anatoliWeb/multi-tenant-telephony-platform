import { Injectable } from '@angular/core';
import type { HttpResponse } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ApiClientService } from '../../../api/services/api-client.service';
import type {
  CallEventItem,
  CallLogFilterOptions,
  CallLogFilters,
  CallLogItem,
  CallLogStatistics,
} from '../models/call-log.model';

@Injectable({ providedIn: 'root' })
export class CallLogsApiService {
  constructor(private readonly apiClient: ApiClientService) {}

  listCallLogs(filters: Partial<CallLogFilters>) {
    return this.apiClient.get<CallLogItem[]>('/v1/call-logs', {
      params: this.toListParams(filters),
    });
  }

  getCallLog(callLogId: number) {
    return this.apiClient.get<CallLogItem>(`/v1/call-logs/${callLogId}`);
  }

  getStatistics(filters: Partial<CallLogFilters>) {
    return this.apiClient.get<CallLogStatistics>('/v1/call-logs/statistics', {
      params: this.toStatisticsParams(filters),
    });
  }

  getEvents(callLogId: number) {
    return this.apiClient.get<CallEventItem[]>(`/v1/call-logs/${callLogId}/events`);
  }

  exportCallLogs(filters: Partial<CallLogFilters>): Observable<HttpResponse<Blob>> {
    return this.apiClient.download('/v1/call-logs/export', {
      params: this.toListParams(filters),
    });
  }

  filterOptions() {
    return this.apiClient.get<CallLogFilterOptions>('/v1/call-logs/filter-options');
  }

  private toListParams(filters: Partial<CallLogFilters>): Record<string, string | number> {
    const params: Record<string, string | number> = {
      page: filters.page ?? 1,
      per_page: filters.per_page ?? 15,
    };

    for (const key of ['search', 'direction', 'status', 'disposition', 'user', 'date_from', 'date_to'] as const) {
      const value = filters[key];
      if (typeof value === 'string' && value.trim()) {
        params[key] = value.trim();
      }
    }

    return params;
  }

  private toStatisticsParams(filters: Partial<CallLogFilters>): Record<string, string> {
    const params: Record<string, string> = {};

    for (const key of ['date_from', 'date_to'] as const) {
      const value = filters[key];
      if (typeof value === 'string' && value.trim()) {
        params[key] = value.trim();
      }
    }

    return params;
  }
}
