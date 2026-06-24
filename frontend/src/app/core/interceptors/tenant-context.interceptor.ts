import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { TenantContextService } from '../services/tenant-context.service';

export const tenantContextInterceptor: HttpInterceptorFn = (request, next) => {
  const tenantContext = inject(TenantContextService);

  if (request.headers.has('X-Skip-Tenant-ID')) {
    return next(
      request.clone({
        headers: request.headers.delete('X-Skip-Tenant-ID'),
      }),
    );
  }

  const tenantId = tenantContext.activeTenantId;

  if (!tenantId) {
    return next(request);
  }

  return next(
    request.clone({
      setHeaders: {
        'X-Tenant-ID': tenantId,
      },
    }),
  );
};
