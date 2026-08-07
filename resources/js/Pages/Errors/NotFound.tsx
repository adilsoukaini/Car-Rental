import PublicLayout from '@/Layouts/PublicLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, Link } from '@inertiajs/react';

/**
 * Custom 404 page, rendered by the exception handler for any request that
 * resolves to a `NotFoundHttpException` (bootstrap/app.php's
 * `$exceptions->respond`). Rendered inside the real PublicLayout so a
 * visitor who hits a dead link still gets the full storefront shell —
 * header, footer, locale switcher — not Laravel's bare error view.
 *
 * EmptyState-style structure: a large display numeral, a heading, a muted
 * description, and a single primary action back to the homepage. Every
 * color/radius/shadow value comes from the theme token system (Hard Rule 3).
 */
export default function NotFound() {
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <Head title="404" />

            <div className="flex flex-col items-center justify-center gap-4 px-4 py-24 text-center">
                <p className="font-mono text-7xl font-bold text-primary sm:text-8xl" aria-hidden="true">
                    404
                </p>
                <h1 className="font-display text-2xl font-bold text-text sm:text-3xl">
                    {t('Page not found')}
                </h1>
                <p className="max-w-sm text-sm text-textMuted">
                    {t("The page you're looking for doesn't exist")}
                </p>
                <Link
                    href={route('home')}
                    className="mt-4 inline-flex items-center rounded-interactive bg-primary px-5 py-2.5 font-body text-sm font-semibold text-onPrimary shadow-resting transition-colors hover:bg-primaryHover"
                >
                    {t('Back to homepage')}
                </Link>
            </div>
        </PublicLayout>
    );
}
