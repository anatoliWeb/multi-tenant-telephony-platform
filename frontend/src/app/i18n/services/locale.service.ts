import { Inject, Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { APP_CONFIG, AppEnvironment } from '../../core/tokens/app-config.token';

const LOCALE_STORAGE_KEY = 'dashboard_locale';

@Injectable({ providedIn: 'root' })
export class LocaleService {
  private readonly localeSubject: BehaviorSubject<string>;
  readonly locale$;
  readonly enabledLocales: readonly string[];

  constructor(@Inject(APP_CONFIG) config: AppEnvironment) {
    this.enabledLocales = config.enabledLocales ?? [config.defaultLocale];
    const persisted = window.localStorage.getItem(LOCALE_STORAGE_KEY);
    const preferred = persisted && persisted.length > 0 ? persisted : config.defaultLocale;
    const locale = this.enabledLocales.includes(preferred) ? preferred : config.defaultLocale;
    this.localeSubject = new BehaviorSubject<string>(locale);
    this.locale$ = this.localeSubject.asObservable();
  }

  get currentLocale(): string {
    return this.localeSubject.value;
  }

  setLocale(locale: string): void {
    if (!this.enabledLocales.includes(locale)) return;
    if (this.localeSubject.value === locale) return;
    this.localeSubject.next(locale);
    window.localStorage.setItem(LOCALE_STORAGE_KEY, locale);
  }
}
