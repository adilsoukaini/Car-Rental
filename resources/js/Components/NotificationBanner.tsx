import { useTranslation } from '@/hooks/useTranslation';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    dismissPushBanner,
    getPushState,
    subscribeToPush,
} from '@/pushNotifications';

/**
 * One-time, per-browser, dismissible banner inviting a logged-in user to
 * enable web push notifications.
 *
 * Shown only while:
 *   - a user is logged in (push tokens are always bound to an account), and
 *   - the browser supports the Push API, and
 *   - notification permission hasn't been asked yet ('default'), and
 *   - the user hasn't dismissed the banner for this browser (localStorage,
 *     same per-user keyed pattern as the driver-verification banner).
 *
 * The permission prompt is only ever triggered by the explicit button press
 * (never on page load). Every failure path degrades silently — if subscribing
 * fails the banner stays visible with a small error line so the user can retry.
 */
export default function NotificationBanner() {
    const { t } = useTranslation();
    // Defaults to a logged-out shape: custom error pages (Errors/NotFound,
    // Errors/ServerError) render from the exception handler for unmatched
    // routes, where the shared `auth` prop is absent entirely. The banner must
    // not crash there — guests and unauthenticated contexts never show it.
    const { auth = { user: null } } = usePage<PageProps>().props;
    const [visible, setVisible] = useState(false);
    const [busy, setBusy] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        // Guests can't register a token — nothing to prompt.
        if (!auth.user) {
            return;
        }

        let mounted = true;
        getPushState().then((state) => {
            if (!mounted) {
                return;
            }
            // 'default' = permission never asked. 'granted' means the user
            // already enabled push (no banner needed); 'denied' means a
            // browser-level block the banner can't override.
            if (state.permission === 'default' && !state.dismissed) {
                setVisible(true);
            }
        });

        return () => {
            mounted = false;
        };
    }, [auth.user]);

    const enable = async () => {
        setBusy(true);
        setFailed(false);
        const ok = await subscribeToPush();
        setBusy(false);

        if (ok) {
            dismissPushBanner();
            setVisible(false);
        } else {
            setFailed(true);
        }
    };

    const dismiss = () => {
        dismissPushBanner();
        setVisible(false);
    };

    if (!visible) {
        return null;
    }

    return (
        <div
            role="status"
            className="border-b border-border bg-surface px-4 py-3 sm:px-6 lg:px-8"
        >
            <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <Bell
                        className="h-5 w-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <p className="text-sm text-textMuted">
                        {t(
                            'Get notified about your bookings and reservation updates.',
                        )}
                    </p>
                </div>

                <div className="flex shrink-0 items-center gap-2">
                    {failed && (
                        <p className="hidden text-xs text-danger sm:block">
                            {t('Could not enable notifications. Please try again.')}
                        </p>
                    )}
                    <button
                        type="button"
                        onClick={enable}
                        disabled={busy}
                        className="rounded-interactive bg-primary px-3 py-1.5 text-sm font-semibold text-onPrimary transition-colors hover:bg-primaryHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {busy ? t('Activating...') : t('Enable notifications')}
                    </button>
                    <button
                        type="button"
                        onClick={dismiss}
                        aria-label={t('Dismiss')}
                        className="flex-shrink-0 rounded-interactive p-1 text-textMuted transition-colors hover:bg-background hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                    >
                        <svg
                            className="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
}
