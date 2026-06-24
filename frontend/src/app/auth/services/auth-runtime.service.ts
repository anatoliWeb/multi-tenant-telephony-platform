import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { AuthStateService } from '../../core/services/auth-state.service';
import { TenantContextService } from '../../core/services/tenant-context.service';
import type { SessionAuthPayload } from '../models/session-auth.model';
import { AuthApiService } from './auth-session.service';
import { AuthTokenStorageService } from './auth-token-storage.service';

@Injectable({ providedIn: 'root' })
export class AuthRuntimeService {
  private hydratePromise: Promise<void> | null = null;

  constructor(
    private readonly authApi: AuthApiService,
    private readonly authState: AuthStateService,
    private readonly tenantContext: TenantContextService,
    private readonly tokenStorage: AuthTokenStorageService,
    private readonly router: Router,
  ) {}

  async hydrateAuth(): Promise<void> {
    if (this.authState.isHydrated) {
      return;
    }

    if (this.hydratePromise) {
      return this.hydratePromise;
    }

    this.hydratePromise = (async () => {
      this.authState.setHydrating(true);
      try {
        const token = this.tokenStorage.getToken();
        if (!token) {
          this.authState.clearSession();
          return;
        }

        const payload = await firstValueFrom(this.authApi.me());
        this.authState.setSession(payload);
        await this.tenantContext.hydrateTenantContext();
      } catch {
        // Guest 401 is normal; hydration must never break app bootstrap.
        this.tokenStorage.clearToken();
        this.authState.clearSession();
        this.tenantContext.clear();
      } finally {
        this.authState.setHydrating(false);
        this.hydratePromise = null;
      }
    })();

    return this.hydratePromise;
  }

  async login(credentials: { email: string; password: string; remember: boolean }): Promise<SessionAuthPayload> {
    this.authState.setAuthLoading(true);
    try {
      const payload = await firstValueFrom(this.authApi.login(credentials));
      if (payload.token) {
        this.tokenStorage.setToken(payload.token);
      }
      this.authState.setSession(payload);
      await this.tenantContext.hydrateTenantContext();
      return payload;
    } catch (error) {
      this.tokenStorage.clearToken();
      this.tenantContext.clear();
      throw error;
    } finally {
      this.authState.setAuthLoading(false);
    }
  }

  async logout(): Promise<void> {
    this.authState.setAuthLoading(true);
    try {
      await firstValueFrom(this.authApi.logout());
    } catch {
      // If token is already expired/revoked we still complete local logout.
    } finally {
      this.tokenStorage.clearToken();
      this.authState.clearSession();
      this.tenantContext.clear();
      this.authState.setAuthLoading(false);
      await this.router.navigateByUrl('/login');
    }
  }
}
