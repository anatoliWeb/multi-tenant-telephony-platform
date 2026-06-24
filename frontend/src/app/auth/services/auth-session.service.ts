import { Injectable } from '@angular/core';
import { map } from 'rxjs';
import { ApiClientService } from '../../api/services/api-client.service';
import type { ApiResponse } from '../../api/models/api-response.model';
import type { SessionAuthPayload } from '../models/session-auth.model';

@Injectable({ providedIn: 'root' })
export class AuthApiService {
  constructor(private readonly apiClient: ApiClientService) {}

  me() {
    return this.apiClient
      .get<SessionAuthPayload>('/v1/auth/me')
      .pipe(map((response: ApiResponse<SessionAuthPayload>) => response.data ?? {
        token: null,
        user: null,
        permissions: [],
        platform_permissions: [],
        tenant_permissions: [],
        roles: [],
      }));
  }

  login(credentials: { email: string; password: string; remember: boolean }) {
    return this.apiClient
      .post<SessionAuthPayload, { email: string; password: string; remember: boolean }>(
        '/v1/auth/token',
        credentials,
      )
      .pipe(map((response: ApiResponse<SessionAuthPayload>) => response.data ?? {
        token: null,
        user: null,
        permissions: [],
        platform_permissions: [],
        tenant_permissions: [],
        roles: [],
      }));
  }

  logout() {
    return this.apiClient.post<unknown, Record<string, never>>('/v1/auth/logout', {});
  }
}
