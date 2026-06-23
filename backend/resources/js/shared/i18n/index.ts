import { createI18n } from 'vue-i18n';

import { FALLBACK_LOCALE, LOCALE_STORAGE_KEY, type LocaleCode } from './config';
import { getDefaultLocale, getEnabledLocales, isLocaleEnabled } from './helpers';
import enCommon from './locales/en/common';
import deCommon from './locales/de/common';
import ukCommon from './locales/uk/common';

export type SupportedLocale = LocaleCode;

/**
 * Reactive locale switching foundation.
 *
 * Locale is stored as Vue-i18n global composer ref. UI must always update this
 * reactive ref (not cached constants) so translated text re-renders instantly.
 */
export const getStoredLocale = (): SupportedLocale => {
  const stored = window.localStorage.getItem(LOCALE_STORAGE_KEY);
  if (stored && isLocaleEnabled(stored)) {
    return stored;
  }

  return getDefaultLocale();
};

export const setStoredLocale = (locale: SupportedLocale): void => {
  if (!isLocaleEnabled(locale)) {
    window.localStorage.setItem(LOCALE_STORAGE_KEY, getDefaultLocale());
    return;
  }

  window.localStorage.setItem(LOCALE_STORAGE_KEY, locale);
};

const localeMessages = {
  en: { common: enCommon },
  uk: { common: ukCommon },
  de: { common: deCommon },
} as const;

const enabledMessages = Object.fromEntries(
  getEnabledLocales().map((item) => [item.code, localeMessages[item.code]]),
) as Record<SupportedLocale, (typeof localeMessages)[SupportedLocale]>;

export const i18n = createI18n({
  legacy: false,
  locale: getStoredLocale(),
  fallbackLocale: FALLBACK_LOCALE,
  messages: enabledMessages,
});

export { getAvailableLocales, getEnabledLocales } from './helpers';
