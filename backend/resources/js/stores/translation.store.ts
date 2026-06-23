import { nextTick } from 'vue'
import { defineStore } from 'pinia'

import { i18n, SupportedLocale, setStoredLocale } from '../shared/i18n'

import { translationService } from '../services/translation'
import { useGlobalLoadingStore } from './global-loading.store'

import type { DynamicTranslations } from '../services/translation'

interface TranslationState {
    locale: SupportedLocale
    translations: DynamicTranslations
    isLoaded: boolean
    isLoading: boolean
    pendingSwitchLocale: SupportedLocale | null
}

const normalizeGroupKey = (
    group: string,
    key: string
): string => {
    if (key.startsWith(`${group}.`)) {
        return key.slice(group.length + 1)
    }

    const singularGroup = group.endsWith('s')
        ? group.slice(0, -1)
        : group

    if (key.startsWith(`${singularGroup}.`)) {
        return key.slice(singularGroup.length + 1)
    }

    return key
}

const setNestedValue = (
    target: Record<string, unknown>,
    path: string,
    value: string
): void => {
    const segments = path.split('.').filter(Boolean)

    if (segments.length === 0) {
        return
    }

    let cursor: Record<string, unknown> = target

    for (let index = 0; index < segments.length - 1; index += 1) {
        const segment = segments[index]
        const next = cursor[segment]

        if (!next || typeof next !== 'object') {
            cursor[segment] = {}
        }

        cursor = cursor[segment] as Record<string, unknown>
    }

    const finalKey = segments[segments.length - 1]
    cursor[finalKey] = value
}

const normalizeGroupTranslations = (
    group: string,
    items: Record<string, string>
): Record<string, unknown> => {
    const normalized: Record<string, unknown> = {}

    Object.entries(items).forEach(([rawKey, rawValue]) => {
        const normalizedKey = normalizeGroupKey(group, rawKey)
        setNestedValue(normalized, normalizedKey, rawValue)
    })

    return normalized
}

export const useTranslationStore = defineStore(
    'translation',
    {
        state: (): TranslationState => ({
            locale: 'en',

            translations: {},

            isLoaded: false,
            isLoading: false,
            pendingSwitchLocale: null,
        }),

        actions: {
            /**
             * Clear runtime translation state.
             *
             * WHY:
             * Logout should drop user-scoped runtime state so the next session
             * starts from a clean bootstrap path without stale locale payloads.
             */
            resetState(): void {
                this.translations = {}
                this.isLoaded = false
                this.isLoading = false
                this.pendingSwitchLocale = null
                this.locale = 'en'
                i18n.global.locale.value = 'en'
            },

            /**
             * Load runtime translations from backend.
             */
            async loadTranslations(
                locale: SupportedLocale
            ): Promise<void> {

                if (this.isLoading) {
                    return
                }

                this.isLoading = true

                try {
                    const response = await translationService
                        .load(locale)

                    /*
                    |--------------------------------------------------------------------------
                    | Backend payload
                    |--------------------------------------------------------------------------
                    */

                    const payload = response

                    if (!payload) {
                        return
                    }

                    const resolvedLocale = payload.locale

                    this.translations = payload.translations

                    /*
                    |--------------------------------------------------------------------------
                    | Merge into vue-i18n runtime
                    |--------------------------------------------------------------------------
                    */

                    Object.entries(payload.translations).forEach(([group, items]) => {
                        const normalizedItems = normalizeGroupTranslations(group, items)

                        i18n.global.mergeLocaleMessage(
                            resolvedLocale,
                            {
                                [group]: normalizedItems,
                            }
                        )
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Activate locale
                    |--------------------------------------------------------------------------
                    */

                    i18n.global.locale.value = resolvedLocale
                    this.locale = resolvedLocale

                    this.isLoaded = true

                } catch (error) {
                    /**
                     * Bootstrap/localization must be resilient for guest flows.
                     *
                     * WHY:
                     * Runtime DB translations are additive. When preload fails
                     * (network/auth/transient), static bundled i18n messages
                     * must keep the app usable instead of hard-failing startup.
                     */
                    this.isLoaded = false

                    if (import.meta.env.DEV) {
                        console.warn('[i18n] loadTranslations failed; using static fallback messages', {
                            locale,
                            error,
                            activeI18nLocale: i18n.global.locale.value,
                        })
                    }
                } finally {

                    this.isLoading = false
                }
            },

            /**
             * Change active application locale.
             */
            async switchLocale(
                locale: SupportedLocale
            ): Promise<void> {

                /*
                |--------------------------------------------------------------------------
                | Avoid duplicate switching
                |--------------------------------------------------------------------------
                */

                if (this.locale === locale) {
                    return
                }

                this.pendingSwitchLocale = locale
                const globalLoadingStore = useGlobalLoadingStore()
                const loadingToken = globalLoadingStore.begin(
                    'Switching language...',
                    'locale',
                    500,
                )

                /**
                 * Locale must be switched before API requests so centralized
                 * Accept-Language propagation sends the target locale.
                 */
                i18n.global.locale.value = locale
                this.locale = locale

                /*
                |--------------------------------------------------------------------------
                | Load runtime translations
                |--------------------------------------------------------------------------
                */

                try {
                    await this.loadTranslations(locale)
                    await nextTick()

                    /*
                    |--------------------------------------------------------------------------
                    | Persist locale
                    |--------------------------------------------------------------------------
                    */

                    setStoredLocale(this.locale)

                } finally {
                    await globalLoadingStore.end(loadingToken)
                    this.pendingSwitchLocale = null
                }
            },
        },
    }
)
