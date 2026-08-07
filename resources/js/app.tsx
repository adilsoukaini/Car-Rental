import '../css/app.css';
import './bootstrap';

import ToastContainer from '@/Components/Toast';
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
}

// The public API only exports createInertiaApp itself, not the internal
// SetupOptions/InertiaAppProps types its `setup` callback receives — derive
// them structurally instead of reaching into an unexported subpath.
type SetupCallback = NonNullable<Parameters<typeof createInertiaApp<SharedProps>>[0]['setup']>;
type SetupArgs = Parameters<SetupCallback>[0];

/**
 * Holds the resolved themeData outside the Inertia component tree (where
 * usePage() is unavailable — see ThemeProvider's own docblock) — reads the
 * initial value from the first page's Inertia props, then stays in sync via
 * router.on('navigate') so a theme activated in the admin panel takes
 * effect on this visitor's very next navigation, with zero rebuild.
 */
function Root({ App, props }: { App: SetupArgs['App']; props: SetupArgs['props'] }) {
    const initial = props.initialPage.props.themeData ?? fallbackSemantic;
    const [themeData, setThemeData] = useState<Semantic>(initial);

    useEffect(() => {
        return router.on('navigate', (event) => {
            const next = (event.detail.page.props as SharedProps).themeData;

            if (next) {
                setThemeData(next);
            }
        });
    }, []);

    return (
        <ThemeProvider themeData={themeData}>
            <App {...props} />
            <ToastContainer />
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
        color: '#4B5563',
    },
});
