import { Component, OnDestroy, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { distinctUntilChanged, map, skip, Subscription } from 'rxjs';
import { ApiClientService } from '../../../../api/services/api-client.service';
import { AuthStateService } from '../../../../core/services/auth-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { RealtimeService } from '../../../../realtime/services/realtime.service';

@Component({
  selector: 'app-dashboard-home',
  templateUrl: './dashboard-home.component.html',
  styleUrls: ['./dashboard-home.component.scss'],
  standalone: false,
})
export class DashboardHomeComponent implements OnInit, OnDestroy {
  private static readonly PRESENCE_DASHBOARD_CHANNEL = 'presence-dashboard';
  readonly realtimeStatus$;
  readonly realtimeEvents$;
  readonly realtimeCount$;
  readonly realtimeActivityCount$;
  readonly onlineUsersCount$;
  readonly dashboardPresenceCount$;

  isDispatching = false;
  readonly user$;
  readonly permissions$;
  readonly roles$;
  readonly isAdmin: boolean;
  statsUsers = 0;
  statsTokens = 0;
  statsRecentActivity = 0;
  isStatsRefreshing = false;
  lastStatsRefreshAt: string | null = null;
  private realtimeRefreshTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly subscriptions = new Subscription();

  constructor(
    private readonly realtime: RealtimeService,
    private readonly apiClient: ApiClientService,
    private readonly authState: AuthStateService,
    private readonly permissionService: PermissionService,
  ) {
    this.realtimeStatus$ = this.realtime.status$;
    this.realtimeEvents$ = this.realtime.events$;
    this.realtimeCount$ = this.realtime.events$.pipe(map((events) => events.length));
    this.realtimeActivityCount$ = this.realtime.activityEvents$.pipe(map((events) => events.length));
    this.onlineUsersCount$ = this.realtime.onlineUsers$.pipe(map((users) => users.length));
    this.dashboardPresenceCount$ = this.realtime.dashboardPresence$.pipe(map((users) => users.length));
    this.user$ = this.authState.user$;
    this.permissions$ = this.authState.permissions$;
    this.roles$ = this.authState.roles$;
    this.isAdmin = this.permissionService.hasRole('admin');
  }

  ngOnInit(): void {
    this.realtime.connect();
    this.realtime.joinPresence(DashboardHomeComponent.PRESENCE_DASHBOARD_CHANNEL);
    void this.refreshDashboardStats();

    this.subscriptions.add(
      this.realtime.events$
        .pipe(
          map((events) => events[0]?.created_at ?? null),
          distinctUntilChanged(),
          skip(1),
        )
        .subscribe(() => {
          this.scheduleStatsRefresh();
        }),
    );
  }

  async dispatchTestNotification(): Promise<void> {
    if (this.isDispatching) return;

    this.isDispatching = true;
    try {
      await firstValueFrom(
        this.apiClient.post<{ dispatched: boolean }, { type: string; title: string; message: string }>('/v1/realtime/notify', {
          type: 'info',
          title: 'Realtime smoke test',
          message: 'Angular received a live websocket event.',
        }),
      );
    } finally {
      this.isDispatching = false;
    }
  }

  ngOnDestroy(): void {
    this.realtime.leavePresence(DashboardHomeComponent.PRESENCE_DASHBOARD_CHANNEL);
    this.subscriptions.unsubscribe();

    if (this.realtimeRefreshTimer) {
      clearTimeout(this.realtimeRefreshTimer);
      this.realtimeRefreshTimer = null;
    }
  }

  private scheduleStatsRefresh(): void {
    if (this.realtimeRefreshTimer) {
      clearTimeout(this.realtimeRefreshTimer);
    }

    this.realtimeRefreshTimer = setTimeout(() => {
      void this.refreshDashboardStats();
    }, 1500);
  }

  private async refreshDashboardStats(): Promise<void> {
    if (this.isStatsRefreshing) {
      return;
    }

    this.isStatsRefreshing = true;
    try {
      const response = await firstValueFrom(
        this.apiClient.get<{
          users: number;
          tokens: number;
          recent_activity?: unknown[];
        }>('/v1/stats'),
      );

      const payload = response.data ?? { users: 0, tokens: 0, recent_activity: [] };
      this.statsUsers = payload.users ?? 0;
      this.statsTokens = payload.tokens ?? 0;
      this.statsRecentActivity = Array.isArray(payload.recent_activity) ? payload.recent_activity.length : 0;
      this.lastStatsRefreshAt = new Date().toISOString();
    } finally {
      this.isStatsRefreshing = false;
    }
  }
}
