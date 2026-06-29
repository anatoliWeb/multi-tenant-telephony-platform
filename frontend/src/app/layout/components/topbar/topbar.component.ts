import { Component, EventEmitter, Input, Output } from '@angular/core';
import type { TenantSelectionItem } from '../../../core/models/tenant-context.model';

@Component({
  selector: 'app-topbar',
  templateUrl: './topbar.component.html',
  styleUrls: ['./topbar.component.scss'],
  standalone: false,
})
export class TopbarComponent {
  @Input() currentLocale = 'en';
  @Input() userName = '';
  @Input() locales: readonly string[] = ['en', 'uk', 'de'];
  @Input() tenants: readonly TenantSelectionItem[] | null = [];
  @Input() activeTenantId: string | null = null;
  @Output() localeChange = new EventEmitter<string>();
  @Output() tenantChange = new EventEmitter<string>();
  @Output() logout = new EventEmitter<void>();

  onLocaleChange(event: Event): void {
    this.localeChange.emit((event.target as HTMLSelectElement).value);
  }

  onTenantChange(event: Event): void {
    this.tenantChange.emit((event.target as HTMLSelectElement).value);
  }

  tenantLabel(tenant: TenantSelectionItem): string {
    if ('tenant' in tenant) {
      return tenant.tenant?.name ?? tenant.tenant?.slug ?? tenant.id;
    }

    return tenant.name;
  }

  tenantId(tenant: TenantSelectionItem): string {
    return this.resolveTenantId(tenant);
  }

  readonly trackTenant = (_index: number, tenant: TenantSelectionItem): string => this.resolveTenantId(tenant);

  private resolveTenantId(tenant: TenantSelectionItem): string {
    if ('tenant' in tenant && tenant.tenant) {
      return tenant.tenant.id;
    }

    return tenant.id;
  }
}
