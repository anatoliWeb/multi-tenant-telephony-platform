import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { CallLogsApiService } from './call-logs-api.service';
import type {
  CallEventItem,
  CallLogFilters,
  CallLogItem,
  CallLogPaginationMeta,
  CallLogStatistics,
  CallLogUserOption,
} from '../models/call-log.model';

const DEFAULT_FILTERS: CallLogFilters = {
  search: '',
  direction: '',
  status: '',
  disposition: '',
  user: '',
  date_from: '',
  date_to: '',
  page: 1,
  per_page: 15,
};

const EMPTY_STATS: CallLogStatistics = {
  window: { date_from: '', date_to: '' },
  total_calls: 0,
  answered_calls: 0,
  missed_calls: 0,
  failed_calls: 0,
  inbound_calls: 0,
  outbound_calls: 0,
  internal_calls: 0,
  total_talk_seconds: 0,
  average_talk_seconds: 0,
  answer_rate: 0,
  calls_by_day: [],
  calls_by_status: [],
  calls_by_direction: [],
  top_users: [],
};

@Injectable({ providedIn: 'root' })
export class CallLogsStateService {
  private readonly callLogsSubject = new BehaviorSubject<CallLogItem[]>([]);
  private readonly activeCallLogSubject = new BehaviorSubject<CallLogItem | null>(null);
  private readonly eventsSubject = new BehaviorSubject<CallEventItem[]>([]);
  private readonly usersSubject = new BehaviorSubject<CallLogUserOption[]>([]);
  private readonly filtersSubject = new BehaviorSubject<CallLogFilters>({ ...DEFAULT_FILTERS });
  private readonly paginationSubject = new BehaviorSubject<CallLogPaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_FILTERS.per_page,
    total: 0,
  });
  private readonly statisticsSubject = new BehaviorSubject<CallLogStatistics>({ ...EMPTY_STATS });
  private readonly loadingSubject = new BehaviorSubject<boolean>(false);
  private readonly detailLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly statisticsLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly optionsLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly exportingSubject = new BehaviorSubject<boolean>(false);
  private readonly errorSubject = new BehaviorSubject<string | null>(null);
  private requestVersion = 0;

  readonly callLogs$ = this.callLogsSubject.asObservable();
  readonly activeCallLog$ = this.activeCallLogSubject.asObservable();
  readonly events$ = this.eventsSubject.asObservable();
  readonly users$ = this.usersSubject.asObservable();
  readonly filters$ = this.filtersSubject.asObservable();
  readonly pagination$ = this.paginationSubject.asObservable();
  readonly statistics$ = this.statisticsSubject.asObservable();
  readonly loading$ = this.loadingSubject.asObservable();
  readonly detailLoading$ = this.detailLoadingSubject.asObservable();
  readonly statisticsLoading$ = this.statisticsLoadingSubject.asObservable();
  readonly optionsLoading$ = this.optionsLoadingSubject.asObservable();
  readonly exporting$ = this.exportingSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();

  constructor(private readonly callLogsApi: CallLogsApiService) {}

  get filters(): CallLogFilters {
    return this.filtersSubject.value;
  }

  async init(loadUserOptions = true): Promise<void> {
    await Promise.all([
      this.loadCallLogs(),
      this.loadStatistics(),
      loadUserOptions ? this.loadFilterOptions() : Promise.resolve(),
    ]);
  }

  async loadCallLogs(): Promise<void> {
    const version = ++this.requestVersion;
    this.loadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callLogsApi.listCallLogs(this.filtersSubject.value));
      if (version !== this.requestVersion) {
        return;
      }

      this.callLogsSubject.next(Array.isArray(response.data) ? response.data : []);
      this.paginationSubject.next({
        current_page: Number(response.meta?.['current_page'] ?? this.filtersSubject.value.page),
        last_page: Number(response.meta?.['last_page'] ?? 1),
        per_page: Number(response.meta?.['per_page'] ?? this.filtersSubject.value.per_page),
        total: Number(response.meta?.['total'] ?? 0),
      });

      const activeId = this.activeCallLogSubject.value?.id ?? null;
      if (activeId && !this.callLogsSubject.value.some((item) => item.id === activeId)) {
        this.activeCallLogSubject.next(null);
        this.eventsSubject.next([]);
      }
    } catch (error) {
      if (version !== this.requestVersion) {
        return;
      }

      this.callLogsSubject.next([]);
      this.paginationSubject.next({
        current_page: this.filtersSubject.value.page,
        last_page: 1,
        per_page: this.filtersSubject.value.per_page,
        total: 0,
      });
      this.errorSubject.next(this.toSafeError(error, 'Failed to load call logs.'));
    } finally {
      if (version === this.requestVersion) {
        this.loadingSubject.next(false);
      }
    }
  }

  async loadStatistics(): Promise<void> {
    this.statisticsLoadingSubject.next(true);

    try {
      const response = await firstValueFrom(this.callLogsApi.getStatistics(this.filtersSubject.value));
      this.statisticsSubject.next(response.data ?? { ...EMPTY_STATS });
    } catch {
      this.statisticsSubject.next({ ...EMPTY_STATS });
    } finally {
      this.statisticsLoadingSubject.next(false);
    }
  }

  async loadFilterOptions(): Promise<void> {
    this.optionsLoadingSubject.next(true);

    try {
      const response = await firstValueFrom(this.callLogsApi.filterOptions());
      this.usersSubject.next(Array.isArray(response.data?.users) ? response.data.users : []);
    } catch {
      this.usersSubject.next([]);
    } finally {
      this.optionsLoadingSubject.next(false);
    }
  }

  async exportCallLogs(): Promise<void> {
    this.exportingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callLogsApi.exportCallLogs(this.filtersSubject.value));
      const blob = response.body ?? new Blob([], { type: response.headers.get('content-type') ?? 'text/csv;charset=UTF-8' });
      this.downloadBlob(blob, this.resolveExportFilename(response.headers.get('content-disposition')));
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to export call logs.'));
    } finally {
      this.exportingSubject.next(false);
    }
  }

  async openCallLog(callLogId: number): Promise<void> {
    this.detailLoadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const [callResponse, eventsResponse] = await Promise.all([
        firstValueFrom(this.callLogsApi.getCallLog(callLogId)),
        firstValueFrom(this.callLogsApi.getEvents(callLogId)),
      ]);

      this.activeCallLogSubject.next(callResponse.data ?? null);
      this.eventsSubject.next(Array.isArray(eventsResponse.data) ? eventsResponse.data : []);
    } catch (error) {
      this.activeCallLogSubject.next(null);
      this.eventsSubject.next([]);
      this.errorSubject.next(this.toSafeError(error, 'Failed to load call details.'));
    } finally {
      this.detailLoadingSubject.next(false);
    }
  }

  selectCallLog(callLog: CallLogItem | null): void {
    this.activeCallLogSubject.next(callLog);
  }

  async setSearch(value: string): Promise<void> {
    this.patchFilters({ search: value, page: 1 });
    await this.reloadVisibleData();
  }

  async setDirection(value: string): Promise<void> {
    this.patchFilters({ direction: value, page: 1 });
    await this.reloadVisibleData();
  }

  async setStatus(value: string): Promise<void> {
    this.patchFilters({ status: value, page: 1 });
    await this.reloadVisibleData();
  }

  async setDisposition(value: string): Promise<void> {
    this.patchFilters({ disposition: value, page: 1 });
    await this.reloadVisibleData();
  }

  async setUser(value: string): Promise<void> {
    this.patchFilters({ user: value, page: 1 });
    await this.reloadVisibleData();
  }

  async setDateRange(dateFrom: string, dateTo: string): Promise<void> {
    this.patchFilters({ date_from: dateFrom, date_to: dateTo, page: 1 });
    await this.reloadVisibleData();
  }

  async setPage(page: number): Promise<void> {
    this.patchFilters({ page });
    await this.loadCallLogs();
  }

  resetForTenantChange(): void {
    this.requestVersion += 1;
    this.callLogsSubject.next([]);
    this.activeCallLogSubject.next(null);
    this.eventsSubject.next([]);
    this.usersSubject.next([]);
    this.filtersSubject.next({ ...DEFAULT_FILTERS });
    this.paginationSubject.next({
      current_page: 1,
      last_page: 1,
      per_page: DEFAULT_FILTERS.per_page,
      total: 0,
    });
    this.statisticsSubject.next({ ...EMPTY_STATS });
    this.loadingSubject.next(false);
    this.detailLoadingSubject.next(false);
    this.statisticsLoadingSubject.next(false);
    this.optionsLoadingSubject.next(false);
    this.exportingSubject.next(false);
    this.errorSubject.next(null);
  }

  private async reloadVisibleData(): Promise<void> {
    await Promise.all([
      this.loadCallLogs(),
      this.loadStatistics(),
    ]);
  }

  private patchFilters(next: Partial<CallLogFilters>): void {
    this.filtersSubject.next({
      ...this.filtersSubject.value,
      ...next,
    });
  }

  private downloadBlob(blob: Blob, filename: string): void {
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = filename;
    anchor.rel = 'noopener';
    anchor.style.display = 'none';

    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    window.URL.revokeObjectURL(url);
  }

  private resolveExportFilename(contentDisposition: string | null): string {
    if (!contentDisposition) {
      return 'call-logs.csv';
    }

    const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (utf8Match?.[1]) {
      return decodeURIComponent(utf8Match[1]);
    }

    const asciiMatch = contentDisposition.match(/filename="?([^";]+)"?/i);
    if (asciiMatch?.[1]) {
      return asciiMatch[1];
    }

    return 'call-logs.csv';
  }

  private toSafeError(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'message' in error) {
      const message = String((error as { message?: unknown }).message ?? '').trim();
      if (message.length > 0) {
        return message;
      }
    }

    return fallback;
  }
}
