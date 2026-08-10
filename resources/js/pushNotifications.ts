/**
 * Web Push (VAPID) client module for the Car Rental storefront.
 *
 * Centralizes every browser Push API interaction so the UI components
 * (NotificationBanner, NotificationSettings) never touch
 * navigator.serviceWorker / PushManager / Notification directly:
 *
 *   - registerServiceWorker()  — silent SW registration (no permission prompt),
 *                                called once from app.tsx on mount.
 *   - subscribeToPush()        — request permission, subscribe with the VAPID
 *                                public key, and send the subscription to
 *                                POST /api/push/register (platform: 'web').
 *   - unsubscribeFromPush()    — unsubscribe the browser subscription and tell
 *                                the backend to drop it.
 *   - getPushState()           — resolve { permission, subscription } for the
 *                                UI to derive toggle/banner state.
 *
 * Resilience (CLAUDE.md "Resilience patterns" — external dependency fallback):
 * every step is wrapped in try/catch and degrades silently. Push support is a
 * progressive enhancement — if any link in the chain is missing (no Push API,
 * no VAPID key configured, a 401 from the register endpoint, an unhelpful
 * browser) the feature simply doesn't appear; it must never crash a page or
 * throw an uncaught error.
 */

import { router } from '@inertiajs/react';

/** Public VAPID key endpoint — see routes/api.php (api.push.vapid-public-key). */
const VAPID_KEY_URL = '/api/push/vapid-public-key';
const REGISTER_URL = '/api/push/register';
const UNREGISTER_URL = '/api/push/unregister';

/** localStorage key for the per-user "don't ask again" banner dismissal. */
const BANNER_DISMISS_KEY = 'push-banner-dismissed';

/**
 * True when this browser supports the full web push stack (service workers,
 * the PushManager API and the Notifications API). Secure context (HTTPS or
 * localhost) is implied — the Push API is unavailable in insecure contexts,
 * so the feature also silently disappears there.
 */
export function isPushSupported(): boolean {
    return (
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

/**
 * Register the service worker (public/sw.js). Fires on every page load but is
 * a no-op after the first successful registration for this origin. Never
 * requests notification permission — that is always user-triggered.
 */
export async function registerServiceWorker(): Promise<void> {
    if (!isPushSupported()) {
        return;
    }

    try {
        await navigator.serviceWorker.register('/sw.js');
    } catch (error) {
        // Logged, not thrown — SW registration failure must never break a page.
        console.warn('Service worker registration failed', error);
    }
}

/**
 * Resolve the current push state for UI decisions:
 *   - permission: Notification.permission, or 'unsupported' when the browser
 *     can't do push at all (the UI hides entirely in that case).
 *   - subscription: the active PushSubscription (null when none).
 *   - dismissed: whether the user dismissed the banner for this browser
 *     (per-user via localStorage — same pattern as the cookie banner).
 */
export async function getPushState(): Promise<{
    permission: NotificationPermission | 'unsupported';
    subscription: PushSubscription | null;
    dismissed: boolean;
}> {
    if (!isPushSupported()) {
        return { permission: 'unsupported', subscription: null, dismissed: true };
    }

    let dismissed = false;
    try {
        dismissed = window.localStorage.getItem(BANNER_DISMISS_KEY) === '1';
    } catch {
        dismissed = false;
    }

    let subscription: PushSubscription | null = null;
    try {
        const registration = await navigator.serviceWorker.ready;
        subscription = await registration.pushManager.getSubscription();
    } catch {
        subscription = null;
    }

    return {
        permission: Notification.permission,
        subscription,
        dismissed,
    };
}

/** Persist the banner dismissal (localStorage, best-effort). */
export function dismissPushBanner(): void {
    try {
        window.localStorage.setItem(BANNER_DISMISS_KEY, '1');
    } catch {
        // Ignore — storage may be unavailable (private mode).
    }
}

/**
 * Fetch the VAPID public key from the backend. It is public by design (the
 * browser needs it to create the subscription), so this endpoint requires no
 * auth. Returns null when the key isn't configured — the caller then degrades
 * silently.
 */
async function getVapidPublicKey(): Promise<string | null> {
    try {
        const response = await fetch(VAPID_KEY_URL, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            return null;
        }
        const data: { public_key?: string } = await response.json();
        return data.public_key ?? null;
    } catch {
        return null;
    }
}

/**
 * Convert a base64url VAPID public key into the Uint8Array the PushManager
 * subscribe() call requires.
 *
 * The explicit `Uint8Array<ArrayBuffer>` return type is load-bearing: the
 * PushManager's applicationServerKey accepts `BufferSource`, which (under
 * TypeScript 7's stricter generic ArrayBuffer typing) requires a
 * `Uint8Array<ArrayBuffer>`, not the wider `Uint8Array<ArrayBufferLike>` the
 * bare `Uint8Array` annotation would infer.
 */
function urlBase64ToUint8Array(base64: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const base64WithPadding = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64WithPadding);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }
    return output;
}

/**
 * Send the browser's PushSubscription to the backend so the server can push to
 * this device. Uses the same /api/* JSON contract as the mobile app, tagged
 * platform: 'web'. Session-authenticated (the /api/push/* routes carry session
 * middleware for web requests and Bearer-token auth for the mobile app — see
 * routes/api.php). CSRF is excluded for /api/* in bootstrap/app.php.
 */
async function sendSubscriptionToBackend(subscription: PushSubscription): Promise<boolean> {
    try {
        const response = await fetch(REGISTER_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                platform: 'web',
                endpoint: subscription.endpoint,
                expirationTime: subscription.expirationTime,
                keys: {
                    p256dh: subscription.getKey('p256dh')
                        ? btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('p256dh')!)))
                        : '',
                    auth: subscription.getKey('auth')
                        ? btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('auth')!)))
                        : '',
                },
            }),
        });

        if (!response.ok) {
            return false;
        }

        // A 401 (session expired) should re-run auth — the Inertia router's
        // on('error') only fires for Inertia responses, so handle it explicitly:
        // let Inertia handle it by re-issuing the current page (which redirects
        // to login). Best-effort — don't block subscription success on it.
        if (response.status === 401) {
            router.reload();
        }

        return true;
    } catch {
        return false;
    }
}

/**
 * Request notification permission and, once granted, create the push
 * subscription and register it with the backend.
 *
 * Returns a boolean success so the UI can react (e.g. a profile toggle flips
 * on only when this returns true). Every failure path degrades silently.
 */
export async function subscribeToPush(): Promise<boolean> {
    if (!isPushSupported()) {
        return false;
    }

    try {
        // 1. Ask for permission (user gesture).
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            return false;
        }

        // 2. VAPID public key — needed before we can subscribe.
        const publicKey = await getVapidPublicKey();
        if (!publicKey) {
            return false;
        }

        // 3. Create the subscription (idempotent — returns the existing one if
        //    already subscribed).
        const registration = await navigator.serviceWorker.ready;
        const subscription =
            (await registration.pushManager.getSubscription()) ??
            (await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey),
            }));

        // 4. Tell the backend about it.
        return sendSubscriptionToBackend(subscription);
    } catch (error) {
        console.warn('Push subscription failed', error);
        return false;
    }
}

/**
 * Remove this browser's push subscription and tell the backend to drop the
 * row. Returns true on success (including the "nothing to unsubscribe" case).
 */
export async function unsubscribeFromPush(): Promise<boolean> {
    if (!isPushSupported()) {
        return true;
    }

    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            // Tell the backend first so the server stops sending to a
            // subscription we're about to invalidate.
            await fetch(UNREGISTER_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    platform: 'web',
                    endpoint: subscription.endpoint,
                }),
            });
            await subscription.unsubscribe();
        }

        return true;
    } catch (error) {
        console.warn('Push unsubscription failed', error);
        return false;
    }
}
