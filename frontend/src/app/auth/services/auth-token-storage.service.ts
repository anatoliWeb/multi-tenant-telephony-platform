import { Injectable } from '@angular/core';

const AUTH_TOKEN_KEY = 'customer_dashboard.auth_token';

@Injectable({ providedIn: 'root' })
export class AuthTokenStorageService {
  getToken(): string | null {
    return localStorage.getItem(AUTH_TOKEN_KEY);
  }

  setToken(token: string): void {
    localStorage.setItem(AUTH_TOKEN_KEY, token);
  }

  clearToken(): void {
    localStorage.removeItem(AUTH_TOKEN_KEY);
  }
}

