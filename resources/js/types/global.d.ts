export {};

interface ZiggyRouterInstance {
    current(): string | undefined;
    current(name: string, params?: unknown): boolean;
}

declare global {
    function route(): ZiggyRouterInstance;
    function route(name: string, params?: unknown, absolute?: boolean): string;

    interface Window {
        /** Active theme id, injected by app.blade.php from config('site.active_theme'). */
        __THEME__?: string;
    }
}
