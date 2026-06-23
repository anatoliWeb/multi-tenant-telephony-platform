import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthTokenStorageService } from '../../auth/services/auth-token-storage.service';

/**
 * Bearer auth interceptor foundation.
 *
 * WHY:
 * Angular dashboard is API-first and stateless. We centralize token header
 * propagation so feature modules never manually compose Authorization headers.
 */
export const authSessionInterceptor: HttpInterceptorFn = (request, next) => {
  const tokenStorage = inject(AuthTokenStorageService);
  const token = tokenStorage.getToken();

  if (!token) {
    return next(request);
  }

  return next(
    request.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`,
      },
    }),
  );
};
