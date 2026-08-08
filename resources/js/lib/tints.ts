/**
 * Low-opacity tints for theme tokens.
 *
 * Opacity modifiers on CSS-var theme tokens (e.g. `bg-danger/5`) do NOT
 * generate classes in this Tailwind v3 setup — only the solid base classes
 * (bg-danger, border-primary, text-success) and standard-color modifiers
 * (bg-white/10) make it into the compiled CSS. The established pattern for
 * tinted theme colors is therefore an inline `color-mix()` style (see the
 * note on `tintDanger` in Pages/Bookings/checkoutShared.ts). These helpers
 * centralize that for components outside the checkout flow.
 */
export const tintSuccess = { backgroundColor: 'color-mix(in srgb, var(--color-success) 10%, transparent)' };
export const tintWarning = { backgroundColor: 'color-mix(in srgb, var(--color-warning) 10%, transparent)' };
export const tintDanger = { backgroundColor: 'color-mix(in srgb, var(--color-danger) 6%, transparent)' };
export const tintDangerSoft = { backgroundColor: 'color-mix(in srgb, var(--color-danger) 5%, transparent)' };
export const tintPrimarySoft = { backgroundColor: 'color-mix(in srgb, var(--color-primary) 5%, transparent)' };
