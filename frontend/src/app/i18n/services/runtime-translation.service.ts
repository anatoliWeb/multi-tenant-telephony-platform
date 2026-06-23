import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, catchError, finalize, map, of, shareReplay, tap } from 'rxjs';
import { ApiClientService } from '../../api/services/api-client.service';
import type { ApiResponse } from '../../api/models/api-response.model';

export interface RuntimeTranslationPayload {
  locale: string;
  fallback_locale: string;
  translations: Record<string, Record<string, string>>;
}

@Injectable({ providedIn: 'root' })
export class RuntimeTranslationService {
  private payload: RuntimeTranslationPayload | null = null;
  private readonly cache = new Map<string, RuntimeTranslationPayload>();
  private readonly inFlight = new Map<string, Observable<RuntimeTranslationPayload | null>>();
  private readonly revisionSubject = new BehaviorSubject<number>(0);
  readonly revision$ = this.revisionSubject.asObservable();

  constructor(
    private readonly apiClient: ApiClientService,
  ) {}

  preload(locale: string): Observable<RuntimeTranslationPayload | null> {
    const cached = this.cache.get(locale);
    if (cached) {
      this.payload = cached;
      this.revisionSubject.next(this.revisionSubject.value + 1);
      return of(cached);
    }

    const pending = this.inFlight.get(locale);
    if (pending) {
      return pending;
    }

    const request$ = this.apiClient
      .get<RuntimeTranslationPayload>('/v1/translations', {
        params: { locale, frontend: 1 },
      })
      .pipe(
        map((response: ApiResponse<RuntimeTranslationPayload>) => response.data ?? null),
        tap((payload) => {
          this.payload = payload;
          if (payload) {
            this.cache.set(locale, payload);
          }
          this.revisionSubject.next(this.revisionSubject.value + 1);
        }),
        catchError(() => of(null)),
        finalize(() => {
          this.inFlight.delete(locale);
        }),
        shareReplay(1),
      );

    this.inFlight.set(locale, request$);
    return request$;
  }

  get snapshot(): RuntimeTranslationPayload | null {
    return this.payload;
  }
}
