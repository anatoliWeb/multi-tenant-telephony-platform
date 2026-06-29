import { HttpClient, HttpParams } from '@angular/common/http';
import { Inject, Injectable } from '@angular/core';
import { APP_CONFIG, AppEnvironment } from '../../core/tokens/app-config.token';
import type { ApiResponse } from '../models/api-response.model';

interface RequestOptions {
  params?: Record<string, string | number | boolean>;
  headers?: Record<string, string>;
}

@Injectable({ providedIn: 'root' })
export class ApiClientService {
  constructor(
    private readonly http: HttpClient,
    @Inject(APP_CONFIG) private readonly config: AppEnvironment,
  ) {}

  get<TData>(url: string, options?: RequestOptions) {
    const resolved = this.resolveUrl(url);
    return this.http.get<ApiResponse<TData>>(resolved, {
      params: this.toHttpParams(options?.params),
      headers: options?.headers,
    });
  }

  post<TData, TPayload>(url: string, payload: TPayload, options?: RequestOptions) {
    const resolved = this.resolveUrl(url);
    return this.http.post<ApiResponse<TData>>(resolved, payload, {
      params: this.toHttpParams(options?.params),
      headers: options?.headers,
    });
  }

  patch<TData, TPayload>(url: string, payload: TPayload, options?: RequestOptions) {
    const resolved = this.resolveUrl(url);
    return this.http.patch<ApiResponse<TData>>(resolved, payload, {
      params: this.toHttpParams(options?.params),
      headers: options?.headers,
    });
  }

  put<TData, TPayload>(url: string, payload: TPayload, options?: RequestOptions) {
    const resolved = this.resolveUrl(url);
    return this.http.put<ApiResponse<TData>>(resolved, payload, {
      params: this.toHttpParams(options?.params),
      headers: options?.headers,
    });
  }

  delete<TData>(url: string, options?: RequestOptions) {
    const resolved = this.resolveUrl(url);
    return this.http.delete<ApiResponse<TData>>(resolved, {
      params: this.toHttpParams(options?.params),
      headers: options?.headers,
    });
  }

  private resolveUrl(path: string): string {
    const normalizedBase = this.config.apiBaseUrl.replace(/\/+$/, '');
    const normalizedPath = path.replace(/^\/+/, '');

    if (!normalizedPath) {
      return normalizedBase;
    }

    return `${normalizedBase}/${normalizedPath}`;
  }

  private toHttpParams(params?: Record<string, string | number | boolean>): HttpParams | undefined {
    if (!params) return undefined;
    let httpParams = new HttpParams();
    Object.entries(params).forEach(([key, value]) => {
      httpParams = httpParams.set(key, String(value));
    });
    return httpParams;
  }
}
