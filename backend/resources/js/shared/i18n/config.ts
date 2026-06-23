export type LocaleCode = 'en' | 'uk' | 'de';

export interface LocaleConfigItem {
  code: LocaleCode;
  label: string;
  enabled: boolean;
}

/**
 * Centralized localization config.
 *
 * WHY CENTRALIZATION:
 * Locale metadata must stay in one registry so Vue pages, shared components,
 * and future Angular screens rely on the same locale policy and labels.
 */
export const LOCALE_CONFIG: LocaleConfigItem[] = [
  { code: 'en', label: 'English', enabled: true },
  { code: 'uk', label: 'Українська', enabled: true },
  { code: 'de', label: 'Deutsch', enabled: true },
];

export const DEFAULT_LOCALE: LocaleCode = 'en';
export const FALLBACK_LOCALE: LocaleCode = 'en';
export const LOCALE_STORAGE_KEY = 'admin_locale';
