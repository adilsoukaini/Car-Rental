/**
 * Resolve a named Ziggy route to a URL string, falling back to a literal path
 * when the route isn't present in the client-side route list.
 *
 * Ziggy's @routes directive embeds the route list into the page server-side.
 * The list is normally complete, but when an error page is rendered after a
 * boot-time exception only the routes registered up to that point exist —
 * calling `route('home')` then throws during render and unmounts the whole
 * React tree (a blank/white page). Guarding render-time route lookups with this
 * helper keeps the shell renderable even when a route is missing.
 */
export function routeUrl(name: string, params?: unknown, fallback = '/'): string {
    try {
        return route(name, params);
    } catch {
        return fallback;
    }
}
