import '../css/app.css';
import './bootstrap';

import ToastContainer from '@/Components/Toast';
import { registerServiceWorker } from '@/pushNotifications';
import { semantic as fallbackSemantic } from '../theme/active';
import { ThemeProvider } from '../theme/ThemeProvider';
import type { Semantic } from '../theme/semantic';
import { createInertiaApp, router } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { useEffect, useState } from 'react';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

interface SharedProps extends PageProps {
    themeData?: Semantic;
    flash?: { message: string; type: 'success' | 'error' | 'info' } | null;
}

// The public API only exports createInertiaApp itself, not the internal
// SetupOptions/InertiaAppProps types its `setup` callback receives — derive
// them structurally instead of reaching into an unexported subpath.
type SetupCallback = NonNullable<Parameters<typeof createInertiaApp<SharedProps>>[0]['setup']>;
type SetupArgs = Parameters<SetupCallback>[0];

/**
 * Holds the resolved themeData and flash outside the Inertia component tree
 * (where usePage() is unavailable — see ThemeProvider's own docblock) —
 * reads the initial value from the first page's Inertia props, then stays in
 * sync via router.on('navigate') so a theme activated in the admin panel
 * takes effect on this visitor's very next navigation, with zero rebuild.
 */
function Root({ App, props }: { App: SetupArgs['App']; props: SetupArgs['props'] }) {
    const initial = props.initialPage.props.themeData ?? fallbackSemantic;
    const [themeData, setThemeData] = useState<Semantic>(initial);

    const initialFlash = (props.initialPage.props as SharedProps).flash ?? null;
    const [flash, setFlash] = useState(initialFlash);

    useEffect(() => {
        return router.on('navigate', (event) => {
            const pageProps = event.detail.page.props as SharedProps;

            if (pageProps.themeData) {
                setThemeData(pageProps.themeData);
            }

            if (pageProps.flash) {
                setFlash(pageProps.flash);
            }
        });
    }, []);

    // Register the service worker (public/sw.js) on every page load. Silent —
    // never requests notification permission (that's always user-triggered via
    // the NotificationBanner / NotificationSettings components). Degrades
    // silently when Push isn't supported or registration fails (Rule 1).
    useEffect(() => {
        registerServiceWorker();
    }, []);

    return (
        <ThemeProvider themeData={themeData}>
            <App {...props} />
            <ToastContainer flash={flash} />
        </ThemeProvider>
    );
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<Root App={App} props={props} />);
    },
    progress: {
        color: 'var(--color-primary)',
    },
});
