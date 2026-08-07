import { lazy, Suspense } from 'react';

/**
 * Maps the component name strings returned by SlotRegistry::render() (PHP)
 * to actual lazily-imported React components.
 *
 * Add one entry per plugin component registered via SlotRegistry::register().
 * The key must match exactly the string passed to SlotRegistry::register() in
 * the plugin's ServiceProvider.
 */
const registry: Record<string, React.ComponentType<any>> = {
    'Widgets/BookingHistory': lazy(() => import('@/Widgets/BookingHistory')),
};

interface SlotEntry {
    component: string;
    props: Record<string, unknown>;
}

/**
 * Renders all components registered into a named slot.
 *
 * extraProps are merged on top of the server-supplied props — use this to pass
 * client-side callbacks (e.g. onChange handlers) that can't come from PHP.
 * Static props from PHP always win if there's a key collision, so keep extraProp
 * keys unique to the page using them.
 */
export function SlotOutlet({
    slot,
    extraProps = {},
}: {
    slot: SlotEntry[];
    extraProps?: Record<string, unknown>;
}) {
    return (
        <>
            {slot.map((s, i) => {
                const C = registry[s.component];
                return C ? (
                    <Suspense key={i} fallback={null}>
                        <C {...extraProps} {...s.props} />
                    </Suspense>
                ) : null;
            })}
        </>
    );
}
