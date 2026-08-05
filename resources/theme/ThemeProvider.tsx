import { createContext, useContext, useEffect, type ReactNode } from 'react';
import { cssVariables, cssVariablesFrom } from './generate-css-vars';
import type { Semantic } from './semantic';

const ThemeContext = createContext<Record<string, string> | null>(null);

interface ThemeProviderProps {
    children: ReactNode;
    /** Resolved theme data from the server (HandleInertiaRequests shares themeData). */
    themeData: Semantic;
    /** Fine-grained per-component overrides on top of the active theme. */
    overrides?: Record<string, string>;
}

/**
 * Injects all token CSS variables onto <html> whenever themeData changes.
 * Layer order (later wins):
 *   1. cssVariables()     — bundled build-time values from active.ts (includes --c-* tokens)
 *   2. cssVariablesFrom() — runtime DB theme data passed as the themeData prop
 *   3. overrides          — optional per-component fine-tuning
 *
 * themeData is passed from app.tsx, which reads it from the Inertia page props on
 * initial load and keeps it in sync via the router.on('navigate') event. This
 * keeps ThemeProvider outside the Inertia component tree (where usePage() is
 * unavailable) while still reacting to theme changes on navigation.
 */
export function ThemeProvider({ children, themeData, overrides = {} }: ThemeProviderProps) {
    const vars = { ...cssVariables(), ...cssVariablesFrom(themeData), ...overrides };

    useEffect(() => {
        const root = document.documentElement;
        Object.entries(vars).forEach(([key, value]) => root.style.setProperty(key, value));
    }, [JSON.stringify(vars)]);

    return (
        <ThemeContext.Provider value={vars}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    return useContext(ThemeContext);
}
