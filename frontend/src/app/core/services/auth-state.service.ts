import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import type { AuthUser } from '../models/auth-user.model';
import type { SessionAuthPayload } from '../../auth/models/session-auth.model';

@Injectable({ providedIn: 'root' })
export class AuthStateService {
  private readonly userSubject = new BehaviorSubject<AuthUser | null>(null);
  private readonly permissionsSubject = new BehaviorSubject<string[]>([]);
  private readonly rolesSubject = new BehaviorSubject<string[]>([]);
  private readonly hydratedSubject = new BehaviorSubject<boolean>(false);
  private readonly hydratingSubject = new BehaviorSubject<boolean>(false);
  private readonly authLoadingSubject = new BehaviorSubject<boolean>(false);

  readonly user$ = this.userSubject.asObservable();
  readonly permissions$ = this.permissionsSubject.asObservable();
  readonly roles$ = this.rolesSubject.asObservable();
  readonly hydrated$ = this.hydratedSubject.asObservable();
  readonly hydrating$ = this.hydratingSubject.asObservable();
  readonly authLoading$ = this.authLoadingSubject.asObservable();

  get isAuthenticated(): boolean {
    return this.userSubject.value !== null;
  }

  get isHydrated(): boolean {
    return this.hydratedSubject.value;
  }

  get userId(): number | null {
    return this.userSubject.value?.id ?? null;
  }

  setSession(payload: SessionAuthPayload): void {
    this.userSubject.next(payload.user);
    this.permissionsSubject.next(payload.permissions);
    this.rolesSubject.next(payload.roles);
    this.hydratedSubject.next(true);
  }

  clearSession(): void {
    this.userSubject.next(null);
    this.permissionsSubject.next([]);
    this.rolesSubject.next([]);
    this.hydratedSubject.next(true);
  }

  setHydrating(value: boolean): void {
    this.hydratingSubject.next(value);
  }

  setAuthLoading(value: boolean): void {
    this.authLoadingSubject.next(value);
  }

  hasPermission(permission: string): boolean {
    return this.permissionsSubject.value.includes(permission);
  }

  hasRole(role: string): boolean {
    return this.rolesSubject.value.includes(role);
  }
}
