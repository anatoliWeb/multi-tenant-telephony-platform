import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SharedModule } from '../../../../shared/shared.module';
import { TenantContextService } from '../../../../core/services/tenant-context.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { CallLogsStateService } from '../../services/call-logs-state.service';
import type { CallLogItem, CallLogUserOption } from '../../models/call-log.model';

@Component({
  selector: 'app-call-logs-shell',
  templateUrl: './call-logs-shell.component.html',
  styleUrls: ['./call-logs-shell.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule],
})
export class CallLogsShellComponent implements OnInit {
  readonly callLogs$;
  readonly activeCallLog$;
  readonly events$;
  readonly users$;
  readonly filters$;
  readonly pagination$;
  readonly statistics$;
  readonly loading$;
  readonly detailLoading$;
  readonly statisticsLoading$;
  readonly exporting$;
  readonly error$;

  readonly canViewAll: boolean;
  readonly canViewStatistics: boolean;
  readonly canExport: boolean;

  constructor(
    private readonly callLogsState: CallLogsStateService,
    private readonly permissionService: PermissionService,
    private readonly tenantContext: TenantContextService,
  ) {
    this.callLogs$ = this.callLogsState.callLogs$;
    this.activeCallLog$ = this.callLogsState.activeCallLog$;
    this.events$ = this.callLogsState.events$;
    this.users$ = this.callLogsState.users$;
    this.filters$ = this.callLogsState.filters$;
    this.pagination$ = this.callLogsState.pagination$;
    this.statistics$ = this.callLogsState.statistics$;
    this.loading$ = this.callLogsState.loading$;
    this.detailLoading$ = this.callLogsState.detailLoading$;
    this.statisticsLoading$ = this.callLogsState.statisticsLoading$;
    this.exporting$ = this.callLogsState.exporting$;
    this.error$ = this.callLogsState.error$;
    this.canViewAll = this.permissionService.hasPermission('call_logs.view_all');
    this.canViewStatistics = this.permissionService.hasPermission('call_logs.view_statistics');
    this.canExport = this.permissionService.hasPermission('call_logs.export');
  }

  ngOnInit(): void {
    void this.callLogsState.init(this.canViewAll);
  }

  async selectCallLog(callLog: CallLogItem): Promise<void> {
    this.callLogsState.selectCallLog(callLog);
    await this.callLogsState.openCallLog(callLog.id);
  }

  async onSearchChange(value: string): Promise<void> {
    await this.callLogsState.setSearch(value);
  }

  async onDirectionChange(value: string): Promise<void> {
    await this.callLogsState.setDirection(value);
  }

  async onStatusChange(value: string): Promise<void> {
    await this.callLogsState.setStatus(value);
  }

  async onDispositionChange(value: string): Promise<void> {
    await this.callLogsState.setDisposition(value);
  }

  async onUserChange(value: string): Promise<void> {
    await this.callLogsState.setUser(value);
  }

  async onDateRangeChange(dateFrom: string, dateTo: string): Promise<void> {
    await this.callLogsState.setDateRange(dateFrom, dateTo);
  }

  async onPageChange(page: number): Promise<void> {
    await this.callLogsState.setPage(page);
  }

  async onExport(): Promise<void> {
    if (!this.tenantContext.hasTenant()) {
      return;
    }

    await this.callLogsState.exportCallLogs();
  }

  formatDuration(seconds: number): string {
    const safe = Math.max(0, Number(seconds || 0));
    const minutes = Math.floor(safe / 60);
    const remainingSeconds = safe % 60;

    return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
  }

  trackCallLog(_index: number, callLog: CallLogItem): number {
    return callLog.id;
  }

  userOptions(users: CallLogUserOption[] | null | undefined): Array<{ value: string; label: string }> {
    return (users ?? []).map((user) => ({
      value: String(user.id),
      label: user.name,
    }));
  }
}
