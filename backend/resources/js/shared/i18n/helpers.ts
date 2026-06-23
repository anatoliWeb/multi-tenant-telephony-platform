import { DEFAULT_LOCALE, LOCALE_CONFIG, type LocaleCode, type LocaleConfigItem } from './config';

/**
 * Locale helper layer.
 *
 * WHY:
 * UI and runtime should consume helper methods instead of touching config
 * arrays directly. This keeps future backend locale-permission integration
 * isolated and predictable.
 */
export const getAvailableLocales = (): LocaleConfigItem[] => {
  return LOCALE_CONFIG;
};

export const getEnabledLocales = (): LocaleConfigItem[] => {
  return LOCALE_CONFIG.filter((item) => item.enabled);
};

export const isLocaleEnabled = (locale: string): locale is LocaleCode => {
  return getEnabledLocales().some((item) => item.code === locale);
};

export const getDefaultLocale = (): LocaleCode => {
  return DEFAULT_LOCALE;
};

