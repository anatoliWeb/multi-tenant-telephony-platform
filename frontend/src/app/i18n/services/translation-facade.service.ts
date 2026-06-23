import { Injectable } from '@angular/core';
import { LocaleService } from './locale.service';
import { RuntimeTranslationService } from './runtime-translation.service';
import { DE_TRANSLATIONS } from '../translations/de';
import { EN_TRANSLATIONS } from '../translations/en';
import { UK_TRANSLATIONS } from '../translations/uk';

const STATIC_TRANSLATIONS: Record<string, Record<string, string>> = {
  en: EN_TRANSLATIONS,
  uk: UK_TRANSLATIONS,
  de: DE_TRANSLATIONS,
};

@Injectable({ providedIn: 'root' })
export class TranslationFacadeService {
  constructor(
    private readonly localeService: LocaleService,
    private readonly runtimeTranslations: RuntimeTranslationService,
  ) {}

  t(key: string, fallback?: string): string {
    const locale = this.localeService.currentLocale;
    const fromRuntime = this.readRuntimeKey(locale, key);
    if (fromRuntime) return fromRuntime;

    const fromStatic = STATIC_TRANSLATIONS[locale]?.[key] ?? STATIC_TRANSLATIONS['en'][key];
    return fromStatic ?? fallback ?? key;
  }

  private readRuntimeKey(locale: string, key: string): string | null {
    const payload = this.runtimeTranslations.snapshot;
    if (!payload || payload.locale !== locale) return null;

    const segments = key.split('.');
    if (segments.length < 2) return null;

    const group = segments.shift() as string;
    const nestedKey = segments.join('.');
    const groupPayload = payload.translations[group];
    if (!groupPayload) return null;

    return groupPayload[nestedKey] ?? null;
  }
}
