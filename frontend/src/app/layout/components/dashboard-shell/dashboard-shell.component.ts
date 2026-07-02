import { Component, OnDestroy } from '@angular/core';
import { firstValueFrom, Subscription } from 'rxjs';
import { AuthRuntimeService } from '../../../auth/services/auth-runtime.service';
import { AppLoadingService } from '../../../core/services/app-loading.service';
import { AuthStateService } from '../../../core/services/auth-state.service';
import { TenantContextService } from '../../../core/services/tenant-context.service';
import { LocaleService } from '../../../i18n/services/locale.service';
import { RuntimeTranslationService } from '../../../i18n/services/runtime-translation.service';
import { PermissionService } from '../../../rbac/services/permission.service';
import { SipClientService } from '../../../features/call-control/services/sip-client.service';

@Component({
  selector: 'app-dashboard-shell',
  templateUrl: './dashboard-shell.component.html',
  styleUrls: ['./dashboard-shell.component.scss'],
  standalone: false,
})
export class DashboardShellComponent implements OnDestroy {
  readonly enabledLocales: readonly string[];
  readonly tenants$;
  readonly activeTenant$;
  readonly activeTenantId$;
  isSoftphoneOpen = false;
  private readonly subscriptions = new Subscription();

  constructor(
    public readonly authState: AuthStateService,
    public readonly permissionService: PermissionService,
    public readonly localeService: LocaleService,
    public readonly tenantContext: TenantContextService,
    private readonly sipClient: SipClientService,
    private readonly appLoading: AppLoadingService,
    private readonly authRuntime: AuthRuntimeService,
    private readonly runtimeTranslation: RuntimeTranslationService,
  ) {
    this.enabledLocales = this.localeService.enabledLocales;
    this.tenants$ = this.tenantContext.tenants$;
    this.activeTenant$ = this.tenantContext.activeTenant$;
    this.activeTenantId$ = this.tenantContext.activeTenantId$;

    this.subscriptions.add(
      this.sipClient.softphoneOpenRequested$.subscribe(() => {
        this.isSoftphoneOpen = true;
      }),
    );
  }

  get canOpenSoftphone(): boolean {
    return this.tenantContext.hasTenant() && this.permissionService.hasTenantPermission('call_control.view');
  }

  async logout(): Promise<void> {
    this.closeSoftphone();
    await this.authRuntime.logout();
  }

  async switchLocale(locale: string): Promise<void> {
    if (locale === this.localeService.currentLocale) {
      return;
    }
    this.appLoading.show('common.locale.switching', 'locale');
    this.localeService.setLocale(locale);
    try {
      await firstValueFrom(this.runtimeTranslation.preload(locale));
    } finally {
      this.appLoading.hide();
    }
  }

  async switchTenant(tenantId: string): Promise<void> {
    this.closeSoftphone();

    if (!tenantId) {
      this.tenantContext.clearSelection();
      return;
    }

    await this.tenantContext.switchTenant(tenantId);
  }

  openSoftphone(): void {
    this.isSoftphoneOpen = true;
  }

  closeSoftphone(): void {
    this.isSoftphoneOpen = false;
    this.sipClient.resetForTenantChange();
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }
}
