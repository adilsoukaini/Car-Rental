import { useTranslation } from '@/hooks/useTranslation';
import { useEffect, useState } from 'react';
import {
    getPushState,
    isPushSupported,
    subscribeToPush,
    unsubscribeFromPush,
} from '@/pushNotifications';

/**
 * "Notifications" card for the profile page — a toggle that reflects the real
 * browser state (permission + active PushSubscription) and drives the whole
 * subscribe / unsubscribe lifecycle.
 *
 *   - On: request notification permission (user gesture), create the VAPID
 *     subscription and register it with the backend.
 *   - Off: remove the browser subscription and tell the backend to drop the
 *     row.
 *
 * The enabled state is derived, not stored: permission and the subscription
 * are both persisted by the browser, so the toggle stays correct across
 * sessions and even across devices sharing the same account.
 *
 * When the browser can't do push at all, or permission is browser-blocked
 * ('denied' — no API can override that), the toggle is replaced/hidden with an
 * explanation. Styling goes through theme tokens (Hard Rule 3).
 */
export default function NotificationSettings() {
    const { t } = useTranslation();
    const supported = isPushSupported();

    const [enabled, setEnabled] = useState(false);
    const [busy, setBusy] = useState(false);
    const [permission, setPermission] = useState<
        NotificationPermission | 'unsupported'
    >('default');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!supported) {
            setPermission('unsupported');
            return;
        }

        let mounted = true;
        getPushState().then((state) => {
            if (!mounted) {
                return;
            }
            setPermission(state.permission);
            setEnabled(state.permission === 'granted' && state.subscription !== null);
        });

        return () => {
            mounted = false;
        };
    }, [supported]);

    const toggle = async () => {
        if (busy) {
            return;
        }

        setBusy(true);
        setError(null);

        if (enabled) {
            const ok = await unsubscribeFromPush();
            if (ok) {
                setEnabled(false);
            } else {
                setError(t('Could not disable notifications. Please try again.'));
            }
        } else {
            const ok = await subscribeToPush();
            const state = await getPushState();
            setPermission(state.permission);

            if (ok && state.permission === 'granted') {
                setEnabled(true);
            } else {
                setError(t('Could not enable notifications. Please try again.'));
            }
        }

        setBusy(false);
    };

    if (permission === 'unsupported') {
        return (
            <div className="rounded-container bg-surface p-4 shadow-resting sm:p-8">
                <h3 className="text-lg font-semibold text-text">
                    {t('Notifications')}
                </h3>
                <p className="mt-1 text-sm text-textMuted">
                    {t('Push notifications are not supported by this browser.')}
                </p>
            </div>
        );
    }

    const blocked = permission === 'denied';

    return (
        <div className="rounded-container bg-surface p-4 shadow-resting sm:p-8">
            <div className="flex items-center justify-between gap-4">
                <div>
                    <h3 className="text-lg font-semibold text-text">
                        {t('Notifications')}
                    </h3>
                    <p className="mt-1 text-sm text-textMuted">
                        {t('Receive booking and status updates on this device.')}
                    </p>
                    {blocked && (
                        <p className="mt-2 text-xs text-danger">
                            {t(
                                'Notification permission is blocked in this browser.',
                            )}
                        </p>
                    )}
                </div>

                <button
                    type="button"
                    role="switch"
                    aria-checked={enabled}
                    aria-label={t('Notifications')}
                    onClick={toggle}
                    disabled={busy || blocked}
                    className={`relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing disabled:cursor-not-allowed disabled:opacity-50 ${
                        enabled ? 'bg-primary' : 'bg-border'
                    }`}
                >
                    <span
                        className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-resting transition-transform ${
                            enabled ? 'translate-x-6' : 'translate-x-1'
                        }`}
                    />
                </button>
            </div>

            {error && (
                <p className="mt-3 text-sm text-danger" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}
