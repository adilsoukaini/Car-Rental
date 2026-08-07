import PublicLayout from '@/Layouts/PublicLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, Link } from '@inertiajs/react';

/**
 * Custom 500/503 page, rendered by the exception handler for any response
 * with status >= 500 (bootstrap/app.php's `$exceptions->respond`). Same
 * EmptyState-style structure as NotFound, inside the real PublicLayout so
 * the storefront shell stays consistent even when the app itself failed.
 */
export default function ServerError() {
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <Head title="500" />

            <div className="flex flex-col items-center justify-center gap-4 px-4 py-24 text-center">
                <p className="font-mono text-7xl font-bold text-primary sm:text-8xl" aria-hidden="true">
                    500
                </p>
                <h1 className="font-display text-2xl font-bold text-text sm:text-3xl">
                    {t('Server Error')}
                </h1>
                <p className="max-w-sm text-sm text-textMuted">
                    {t('Something went wrong on our end. Please try again later.')}
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
