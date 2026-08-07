import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';

/**
 * Maps the component-name strings LayoutVariantRegistry::activeComponentFor()
 * resolves (PHP) to actual lazily-imported React components.
 *
 * Add one entry per variant registered via LayoutVariantRegistry::register().
 * The key must match exactly the componentName string passed there.
 */
const registry: Record<string, React.ComponentType<any>> = {
    'Layout/VehicleCard/Vertical': lazy(() => import('@/Layout/VehicleCard/Vertical')),
    'Layout/VehicleCard/HorizontalSplit': lazy(() => import('@/Layout/VehicleCard/HorizontalSplit')),
};

/**
 * Renders the active variant for a layout region.
 *
 * Props beyond `name` are passed directly to the variant component and must
 * satisfy the region's contract. TypeScript enforces nothing here at the
 * call site — enforcement is at the variant component's own import of its
 * contract interface.
 */
export function LayoutSlot<P extends object>({ name, ...props }: { name: string } & P) {
    const { activeLayoutVariants } = usePage<PageProps>().props;
    const componentName: string | undefined = activeLayoutVariants?.[name];

    // If no variant is registered for this slot yet (e.g. the plugin that
    // owns the region hasn't been activated, or the layout_settings migration
    // hasn't run), silently render nothing rather than exploding — this
    // matches SlotOutlet's "unknown component names render nothing" contract
    // and prevents a single missing region from crashing the entire page.
    const Component = componentName
        ? (registry[componentName] as React.ComponentType<P> | undefined)
        : undefined;

    if (!Component) {
        return null;
    }

    return (
        <Suspense fallback={null}>
            <Component {...(props as P)} />
        </Suspense>
    );
}
