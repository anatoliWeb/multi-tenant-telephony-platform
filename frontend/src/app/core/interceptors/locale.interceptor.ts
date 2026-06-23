import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { LocaleService } from '../../i18n/services/locale.service';

/**
 * Locale propagation interceptor.
 *
 * WHY:
 * Backend localization middleware reads Accept-Language. Centralizing header
 * propagation keeps endpoints locale-aware without per-request duplication.
 */
export const localeInterceptor: HttpInterceptorFn = (request, next) => {
  const localeService = inject(LocaleService);
  return next(
    request.clone({
      setHeaders: {
        'Accept-Language': localeService.currentLocale,
      },
    }),
  );
};

