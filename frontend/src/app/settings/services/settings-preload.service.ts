import { Injectable } from '@angular/core';
import { Observable, catchError, finalize, map, of, shareReplay, tap } from 'rxjs';
import { ApiClientService } from '../../api/services/api-client.service';
import type { ApiResponse } from '../../api/models/api-response.model';

export interface FrontendSettingsPayload {
  settings: Record<string, unknown>;
}

@Injectable({ providedIn: 'root' })
export class SettingsPreloadService {
  private snapshot: FrontendSettingsPayload = { settings: {} };
  private loaded = false;
  private inFlight$: Observable<FrontendSettingsPayload> | null = null;

  constructor(
    private readonly apiClient: ApiClientService,
  ) {}

  preload(force = false): Observable<FrontendSettingsPayload> {
    if (!force && this.loaded) {
      return of(this.snapshot);
    }

    if (!force && this.inFlight$) {
      return this.inFlight$;
    }

    this.inFlight$ = this.apiClient.get<FrontendSettingsPayload>('/v1/settings/preload').pipe(
      map((response: ApiResponse<FrontendSettingsPayload>) => response.data ?? { settings: {} }),
      tap((payload) => {
        this.snapshot = payload;
        this.loaded = true;
      }),
      catchError(() => of({ settings: {} })),
      finalize(() => {
        this.inFlight$ = null;
      }),
      shareReplay(1),
    );

    return this.inFlight$;
  }

  get current(): FrontendSettingsPayload {
    return this.snapshot;
  }
}
