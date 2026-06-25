import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { AuthTokenStorageService } from '../../auth/services/auth-token-storage.service';
import { AuthStateService } from './auth-state.service';
import { TenantApiService } from './tenant-api.service';
import type { TenantContextPayload, TenantMembershipSummary, TenantSummary } from '../models/tenant-context.model';
import { ChatStateService } from '../../features/chat/services/chat-state.service';
import { ContactsStateService } from '../../features/contacts/services/contacts-state.service';
import { ExtensionsStateService } from '../../features/extensions/services/extensions-state.service';

const ACTIVE_TENANT_KEY = 'admin_active_tenant_id';

@Injectable({ providedIn: 'root' })
export class TenantContextService {
  private readonly tenantsSubject = new BehaviorSubject<TenantMembershipSummary[]>([]);
  private readonly activeTenantSubject = new BehaviorSubject<TenantSummary | null>(null);
  private readonly activeTenantIdSubject = new BehaviorSubject<string | null>(this.readStoredTenantId());

  readonly tenants$ = this.tenantsSubject.asObservable();
  readonly activeTenant$ = this.activeTenantSubject.asObservable();
  readonly activeTenantId$ = this.activeTenantIdSubject.asObservable();

  constructor(
    private readonly tenantApi: TenantApiService,
    private readonly tokenStorage: AuthTokenStorageService,
    private readonly authState: AuthStateService,
    private readonly chatState: ChatStateService,
    private readonly contactsState: ContactsStateService,
    private readonly extensionsState: ExtensionsStateService,
  ) {}

  get activeTenantId(): string | null {
    return this.activeTenantIdSubject.value;
  }

  get activeTenant(): TenantSummary | null {
    return this.activeTenantSubject.value;
  }

  hasTenant(): boolean {
    return this.activeTenantIdSubject.value !== null;
  }

  setActiveTenantId(tenantId: string | null): void {
    this.activeTenantIdSubject.next(tenantId);

    if (tenantId) {
      window.localStorage.setItem(ACTIVE_TENANT_KEY, tenantId);
      return;
    }

    window.localStorage.removeItem(ACTIVE_TENANT_KEY);
  }

  setActiveTenant(tenant: TenantSummary | null): void {
    this.activeTenantSubject.next(tenant);
    this.setActiveTenantId(tenant?.id ?? null);
  }

  clear(): void {
    this.chatState.resetForTenantChange();
    this.contactsState.resetForTenantChange();
    this.extensionsState.resetForTenantChange();
    this.tenantsSubject.next([]);
    this.activeTenantSubject.next(null);
    this.setActiveTenantId(null);
    this.authState.clearTenantPermissions();
  }

  async hydrateTenantContext(): Promise<void> {
    if (!this.tokenStorage.getToken()) {
      this.clear();
      return;
    }

    try {
      const payload = await firstValueFrom(this.tenantApi.listTenants());
      this.syncTenants(payload.tenants);
      this.reconcileActiveTenant(payload.current_tenant_id);
      const selectedTenantId = this.activeTenantIdSubject.value;
      this.authState.setPermissionScopes({
        platform_permissions: payload.platform_permissions ?? [],
        tenant_permissions: payload.tenant_permissions ?? [],
        current_tenant_id: selectedTenantId,
      });
    } catch {
      this.clear();
    }
  }

  async switchTenant(tenantId: string): Promise<TenantContextPayload> {
    this.chatState.resetForTenantChange();
    this.contactsState.resetForTenantChange();
    this.extensionsState.resetForTenantChange();
    const payload = await firstValueFrom(this.tenantApi.switchTenant(tenantId));
    this.activeTenantSubject.next(payload.tenant);
    this.setActiveTenantId(payload.current_tenant_id ?? payload.tenant?.id ?? tenantId);
    this.authState.setPermissionScopes({
      platform_permissions: payload.platform_permissions ?? [],
      tenant_permissions: payload.tenant_permissions ?? [],
      current_tenant_id: payload.current_tenant_id,
    });
    await this.hydrateTenantContext();
    return {
      tenant: payload.tenant,
      membership: payload.membership,
      current_tenant_id: payload.current_tenant_id,
      permissions: payload.permissions ?? [],
      platform_permissions: payload.platform_permissions ?? [],
      tenant_permissions: payload.tenant_permissions ?? [],
    };
  }

  async refreshCurrentTenant(): Promise<void> {
    if (!this.hasTenant()) {
      return this.hydrateTenantContext();
    }

    try {
      const payload = await firstValueFrom(this.tenantApi.currentTenant());
      this.activeTenantSubject.next(payload.tenant);
      this.setActiveTenantId(payload.current_tenant_id ?? payload.tenant?.id ?? this.activeTenantId);
      this.authState.setPermissionScopes({
        platform_permissions: payload.platform_permissions ?? [],
        tenant_permissions: payload.tenant_permissions ?? [],
        current_tenant_id: this.activeTenantId,
      });
    } catch {
      this.clear();
    }
  }

  private syncTenants(tenants: TenantMembershipSummary[]): void {
    this.tenantsSubject.next(tenants);
  }

  private reconcileActiveTenant(currentTenantId: string | null): void {
    const tenants = this.tenantsSubject.value;
    const storedTenantId = this.readStoredTenantId();
    const currentMembership = currentTenantId
      ? tenants.find((membership) => membership.tenant?.id === currentTenantId) ?? null
      : null;
    const storedMembership = storedTenantId
      ? tenants.find((membership) => membership.tenant?.id === storedTenantId) ?? null
      : null;
    const fallbackMembership = tenants[0] ?? null;
    const selectedMembership = currentMembership ?? storedMembership ?? fallbackMembership;
    const candidate = selectedMembership?.tenant ?? null;

    if (this.activeTenantSubject.value?.id !== candidate?.id) {
      this.chatState.resetForTenantChange();
      this.contactsState.resetForTenantChange();
      this.extensionsState.resetForTenantChange();
    }
    this.activeTenantSubject.next(candidate);
    this.setActiveTenantId(candidate?.id ?? null);
  }

  private readStoredTenantId(): string | null {
    return window.localStorage.getItem(ACTIVE_TENANT_KEY);
  }
}
