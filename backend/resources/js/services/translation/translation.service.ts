import { http } from '../api/http'
import { SupportedLocale } from '../../shared/i18n'
export type DynamicTranslations = Record<string, Record<string, string>>

export interface TranslationPayload {
    locale: SupportedLocale
    fallback_locale: SupportedLocale
    translations: DynamicTranslations
    snapshot_token?: string
}

/**
 * Loads runtime translations from Laravel backend.
 *
 * Static translations remain in shared/i18n.
 * Dynamic database translations are loaded through this service
 * and later merged into vue-i18n.
 */
export const translationService = {

    async load(
        _locale: string
    ): Promise<TranslationPayload> {

        const response = await http.get<TranslationPayload>(
            '/v1/translations',
            {
                params: {
                    frontend: 1,
                }
            },
        )

        return response.data
    },

    async loadGroup(
        _locale: string,
        group: string
    ): Promise<TranslationPayload> {

        const response = await http.get<TranslationPayload>(
            '/v1/translations',
            {
                params: {
                    group,
                    frontend: 1,
                },
            },
        )

        return response.data
    },
}
