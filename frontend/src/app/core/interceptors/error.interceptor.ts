import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { catchError, throwError } from 'rxjs';
import type { NormalizedApiError } from '../../api/models/api-response.model';

const normalizeError = (error: HttpErrorResponse): NormalizedApiError => {
  if (error.status === 401) {
    return { status: 401, code: 'unauthorized', message: error.error?.message ?? 'Unauthorized', errors: error.error?.errors ?? null };
  }

  if (error.status === 403) {
    return { status: 403, code: 'forbidden', message: error.error?.message ?? 'Forbidden', errors: error.error?.errors ?? null };
  }

  if (error.status === 422) {
    return { status: 422, code: 'validation', message: error.error?.message ?? 'Validation failed', errors: error.error?.errors ?? null };
  }

  if (error.status >= 500) {
    return { status: error.status, code: 'server', message: error.error?.message ?? 'Server error', errors: error.error?.errors ?? null };
  }

  if (error.status === 0) {
    return { status: 0, code: 'network', message: 'Network error', errors: null };
  }

  return { status: error.status, code: 'unknown', message: error.error?.message ?? 'Request failed', errors: error.error?.errors ?? null };
};

/**
 * Error normalization placeholder.
 *
 * WHY:
 * Establishes one consistent error shape for feature modules before business
 * flows become complex.
 */
export const errorInterceptor: HttpInterceptorFn = (request, next) => {
  return next(request).pipe(
    catchError((error: HttpErrorResponse) => {
      return throwError(() => normalizeError(error));
    }),
  );
};

