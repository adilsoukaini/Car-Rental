import { useTranslation } from '@/hooks/useTranslation';
import { DriverVerification } from '@/types';
import { Link } from '@inertiajs/react';
import { CheckCircle2, Clock, ShieldAlert, XCircle } from 'lucide-react';

/**
 * Driver-verification status card, rendered on the profile page.
 *
 * Verification management lives here (it moved out of the public navbar —
 * it's not something every user needs immediately, only those booking
 * restricted categories). Four states, driven by the shared
 * `driverVerificationStatus` prop (authored in HandleInertiaRequests) plus
 * the latest verification row (for the rejection reason):
 *
 *   none     — never submitted: CTA to start verification
 *   pending  — submitted, awaiting staff review
 *   approved — verified: green check
 *   rejected — reason shown + CTA to resubmit
 *
 * All colors/spacing go through theme tokens (Hard Rule 3); labels go
 * through useTranslation (French is the storefront default).
 */
export default function DriverVerificationCard({
    status,
    verification,
}: {
    status: 'none' | 'pending' | 'approved' | 'rejected' | null;
    verification: DriverVerification | null;
}) {
    const { t } = useTranslation();

    const canSubmitNew = status === 'none' || status === 'rejected';

    const icon = {
        none: <ShieldAlert className="h-5 w-5 text-textMuted" aria-hidden="true" />,
        pending: <Clock className="h-5 w-5 text-warning" aria-hidden="true" />,
        approved: <CheckCircle2 className="h-5 w-5 text-success" aria-hidden="true" />,
        rejected: <XCircle className="h-5 w-5 text-danger" aria-hidden="true" />,
    }[status ?? 'none'];

    const title = {
        none: (
            <span className="text-text">{t('Not verified')}</span>
        ),
        pending: (
            <span className="text-warning">{t('Verification pending review')}</span>
        ),
        approved: (
            <span className="flex items-center gap-1.5 text-success">
                <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                {t('Verified')}
            </span>
        ),
        rejected: (
            <span className="text-danger">{t('Rejected')}</span>
        ),
    }[status ?? 'none'];

    const description = {
        none: t('Optional pre-verification — skip the line at pickup. You can still book without it.'),
        pending: t('We are reviewing your license. Optional — you can still book while it is pending.'),
        approved: t('Your license is pre-verified — skip the line at pickup.'),
        rejected: (
            <span>
                <span>
                    {t('Reason')}:{' '}
                    <span className="font-medium text-text">
                        {verification?.rejection_reason ?? t('Not provided')}
                    </span>
                </span>
                <span className="block">{t('You can resubmit if you’d like — verification is optional.')}</span>
            </span>
        ),
    }[status ?? 'none'];

    return (
        <div className="rounded-container bg-surface p-4 shadow-resting sm:p-8">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex items-start gap-3">
                    <div className="mt-0.5 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-background">
                        {icon}
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <p className="text-sm font-semibold uppercase tracking-wide text-textMuted">
                                {t('Driver’s License')}
                            </p>
                            <span className="rounded-pill bg-background px-2 py-0.5 text-xs font-medium text-textMuted">
                                {t('Optional')}
                            </span>
                        </div>
                        <p className="mt-0.5 font-display text-lg font-bold">{title}</p>
                        <p className="mt-1 max-w-lg text-sm text-textMuted">{description}</p>
                    </div>
                </div>

                {canSubmitNew && (
                    <Link
                        href={route('driver-verification.show')}
                        className="inline-flex shrink-0 items-center justify-center gap-2 rounded-interactive bg-primary px-4 py-2 text-sm font-semibold text-onPrimary shadow-resting transition-colors hover:bg-primaryHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                    >
                        {status === 'rejected' ? t('Resubmit') : t('Pre-verify')}
                    </Link>
                )}
            </div>
        </div>
    );
}
