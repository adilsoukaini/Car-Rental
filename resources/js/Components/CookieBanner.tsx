import { useTranslation } from '@/hooks/useTranslation';
import { useEffect, useState } from 'react';

/**
 * Fixed-bottom cookie consent banner, rendered inside PublicLayout so it
 * appears on every storefront page. Reads acceptance from localStorage so a
 * returning visitor who already accepted never sees it again.
 *
 * SSR-safe: `visible` starts `false` and is only flipped after mount, once
 * localStorage is actually available — no server/client render mismatch.
 */
const STORAGE_KEY = 'cookie-consent-accepted';

export default function CookieBanner() {
    const { t } = useTranslation();
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        setVisible(window.localStorage.getItem(STORAGE_KEY) !== '1');
    }, []);

    const accept = () => {
        window.localStorage.setItem(STORAGE_KEY, '1');
        setVisible(false);
    };

    if (!visible) {
        return null;
    }

    return (
        <div className="fixed inset-x-0 bottom-0 z-50 px-4 pb-4">
            <div className="mx-auto flex max-w-3xl flex-col items-center justify-between gap-4 rounded-container border border-border bg-surface p-4 shadow-overlay sm:flex-row">
                <p className="text-sm text-textMuted">
                    {t(
                        'We use cookies to improve your experience. By using our site, you agree to our use of cookies.',
                    )}
                </p>
                <button
                    type="button"
                    onClick={accept}
                    className="shrink-0 rounded-interactive bg-primary px-4 py-2 font-body text-sm font-semibold text-onPrimary shadow-resting transition-colors hover:bg-primaryHover"
                >
                    {t('Accept')}
                </button>
            </div>
        </div>
    );
}
