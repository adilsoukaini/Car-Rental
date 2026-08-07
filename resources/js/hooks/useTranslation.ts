import { usePage } from '@inertiajs/react';
import en from '../../../lang/en.json';
import fr from '../../../lang/fr.json';

/**
 * Client-facing FR/EN translations.
 *
 * Keys are canonical English UI strings; `lang/en.json` is the identity
 * mapping and `lang/fr.json` holds the French translations. The active locale
 * is shared by HandleInertiaRequests (`app()->getLocale()`, defaulting to
 * French — the storefront's current language) and can be switched at runtime
 * via the header's FR|EN toggle, which navigates to `?lang=en|fr`.
 */
const translations: Record<string, Record<string, string>> = {
    en,
    fr,
};

export function useTranslation() {
    const { locale } = usePage<{ locale?: string }>().props;
    const lang = locale ?? 'fr'; // default French

    return {
        t: (key: string, fallback?: string) => translations[lang]?.[key] ?? fallback ?? key,
        locale: lang,
    };
}
