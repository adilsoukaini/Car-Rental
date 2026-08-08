import PublicLayout from '@/Layouts/PublicLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { tintDanger, tintDangerSoft, tintPrimarySoft, tintSuccess, tintWarning } from '@/lib/tints';
import { DriverVerification } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import {
    CheckCircle2, Clock, CloudUpload, FileCheck2, ShieldAlert, XCircle,
} from 'lucide-react';

/**
 * Driver-verification submission page — reached from the profile page's
 * status card (the management home), or from a blocked checkout.
 *
 * Shows the current verification status prominently when one already exists,
 * then a form card (license number / country / date of birth / document
 * dropzone). Submission only makes sense when there is no live
 * pending/approved verification, so the form is hidden for those states
 * (re-submission is allowed after a rejection).
 *
 * All colors/spacing go through theme tokens (Hard Rule 3); labels go through
 * useTranslation (French is the storefront default).
 */

const COUNTRIES = [
    'Morocco', 'France', 'Spain', 'Belgium', 'Netherlands', 'Germany',
    'Italy', 'Portugal', 'United Kingdom', 'United States', 'Canada',
    'Switzerland', 'United Arab Emirates', 'Saudi Arabia', 'Algeria',
    'Tunisia', 'Senegal', 'Ivory Coast', 'Mali', 'Mauritania',
] as const;

const inputClass =
    'w-full rounded-interactive border border-border bg-background px-3 py-2.5 text-sm text-text placeholder:text-textMuted focus:border-focusRing focus:outline-none focus:ring-1 focus:ring-focusRing';

export default function Show({ verification }: { verification: DriverVerification | null }) {
    const { t } = useTranslation();
    const canSubmitNew = verification === null || verification.status === 'rejected';

    const [dragActive, setDragActive] = useState(false);
    const [fileName, setFileName] = useState<string | null>(null);

    const { data, setData, post, processing, errors } = useForm<{
        license_number: string;
        license_country: string;
        date_of_birth: string;
        license_document: File | null;
    }>({
        license_number: '',
        license_country: '',
        date_of_birth: '',
        license_document: null,
    });

    const setFile = (file: File | undefined | null) => {
        setData('license_document', file ?? null);
        setFileName(file?.name ?? null);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('driver-verification.store'), {
            forceFormData: true,
        });
    };

    return (
        <PublicLayout>
            <Head title={t('Pre-verification')} />

            <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 className="font-display text-3xl font-bold text-text">
                    {t('Pre-verification')}
                </h1>
                <p className="mt-2 text-sm text-textMuted">
                    {t('Skip the line at pickup — pre-verify your license. Optional.')}
                </p>

                {/* Prominent status card when a submission already exists */}
                {verification && (
                    <div className="mt-6 rounded-container border border-border bg-surface p-6 shadow-resting">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex items-start gap-3">
                                <div
                                    className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full ${
                                        verification.status === 'approved'
                                            ? 'text-success'
                                            : verification.status === 'rejected'
                                              ? 'text-danger'
                                              : 'text-warning'
                                    }`}
                                    style={
                                        verification.status === 'approved'
                                            ? tintSuccess
                                            : verification.status === 'rejected'
                                              ? tintDanger
                                              : tintWarning
                                    }
                                >
                                    {verification.status === 'approved' ? (
                                        <CheckCircle2 className="h-6 w-6" aria-hidden="true" />
                                    ) : verification.status === 'rejected' ? (
                                        <XCircle className="h-6 w-6" aria-hidden="true" />
                                    ) : (
                                        <Clock className="h-6 w-6" aria-hidden="true" />
                                    )}
                                </div>
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-textMuted">
                                        {t('Current status')}
                                    </p>
                                    <p
                                        className={`mt-0.5 font-display text-lg font-bold ${
                                            verification.status === 'approved'
                                                ? 'text-success'
                                                : verification.status === 'rejected'
                                                  ? 'text-danger'
                                                  : 'text-warning'
                                        }`}
                                    >
                                        {verification.status === 'approved'
                                            ? t('Verified')
                                            : verification.status === 'rejected'
                                              ? t('Rejected')
                                              : t('Pending review')}
                                    </p>
                                    <p className="mt-1 text-sm text-textMuted">
                                        {t('License')} {verification.license_number} ·{' '}
                                        {verification.license_country}
                                    </p>
                                    {verification.status === 'rejected' && (
                                        <div
                                            className="mt-3 rounded-interactive border border-danger p-3 text-sm text-danger"
                                            style={tintDangerSoft}
                                        >
                                            <p className="font-semibold">{t('Rejection reason')}</p>
                                            <p className="mt-1">
                                                {verification.rejection_reason ?? t('Not provided')}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {!canSubmitNew && (
                                <div className="flex items-center gap-2 rounded-interactive bg-background px-3 py-2 text-sm text-textMuted">
                                    <FileCheck2 className="h-4 w-4" aria-hidden="true" />
                                    {t('Submitted on')} {new Date(verification.created_at).toLocaleDateString()}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {canSubmitNew ? (
                    <form
                        onSubmit={submit}
                        className="mt-6 space-y-6 rounded-container border border-border bg-surface p-6 shadow-resting"
                    >
                        {/* License number */}
                        <div>
                            <label htmlFor="license_number" className="mb-1.5 block text-sm font-semibold text-text">
                                {t('License number')}
                            </label>
                            <input
                                id="license_number"
                                type="text"
                                value={data.license_number}
                                onChange={(e) => setData('license_number', e.target.value)}
                                className={inputClass}
                                placeholder={t('e.g. AB-123456')}
                                required
                                aria-required="true"
                                aria-invalid={errors.license_number ? 'true' : 'false'}
                                aria-describedby={errors.license_number ? 'license_number-error' : undefined}
                            />
                            {errors.license_number && (
                                <p id="license_number-error" className="mt-1.5 text-sm text-danger">
                                    {errors.license_number}
                                </p>
                            )}
                        </div>

                        {/* License country */}
                        <div>
                            <label htmlFor="license_country" className="mb-1.5 block text-sm font-semibold text-text">
                                {t('License country')}
                            </label>
                            <select
                                id="license_country"
                                value={data.license_country}
                                onChange={(e) => setData('license_country', e.target.value)}
                                className={inputClass}
                                required
                                aria-required="true"
                                aria-invalid={errors.license_country ? 'true' : 'false'}
                                aria-describedby={errors.license_country ? 'license_country-error' : undefined}
                            >
                                <option value="" disabled>
                                    {t('Select a country')}
                                </option>
                                {COUNTRIES.map((country) => (
                                    <option key={country} value={country}>
                                        {country}
                                    </option>
                                ))}
                            </select>
                            {errors.license_country && (
                                <p id="license_country-error" className="mt-1.5 text-sm text-danger">
                                    {errors.license_country}
                                </p>
                            )}
                        </div>

                        {/* Date of birth */}
                        <div>
                            <label htmlFor="date_of_birth" className="mb-1.5 block text-sm font-semibold text-text">
                                {t('Date of birth')}
                            </label>
                            <input
                                id="date_of_birth"
                                type="date"
                                value={data.date_of_birth}
                                onChange={(e) => setData('date_of_birth', e.target.value)}
                                className={inputClass}
                                required
                                aria-required="true"
                                aria-invalid={errors.date_of_birth ? 'true' : 'false'}
                                aria-describedby={errors.date_of_birth ? 'date_of_birth-error' : undefined}
                            />
                            {errors.date_of_birth && (
                                <p id="date_of_birth-error" className="mt-1.5 text-sm text-danger">
                                    {errors.date_of_birth}
                                </p>
                            )}
                        </div>

                        {/* License document — drag-and-drop dropzone */}
                        <div>
                            <span className="mb-1.5 block text-sm font-semibold text-text">
                                {t('License document')}
                            </span>
                            <label
                                htmlFor="license_document"
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragActive(true);
                                }}
                                onDragLeave={() => setDragActive(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setDragActive(false);
                                    setFile(e.dataTransfer.files?.[0]);
                                }}
                                className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-container border-2 border-dashed px-4 py-10 text-center transition-colors focus-within:border-focusRing focus-within:ring-1 focus-within:ring-focusRing ${
                                    dragActive
                                        ? 'border-primary'
                                        : 'border-border bg-background hover:border-primary'
                                }`}
                                style={dragActive ? tintPrimarySoft : undefined}
                            >
                                <CloudUpload
                                    className="h-8 w-8 text-textMuted"
                                    aria-hidden="true"
                                />
                                {fileName ? (
                                    <>
                                        <p className="text-sm font-semibold text-text">{fileName}</p>
                                        <p className="text-xs text-textMuted">
                                            {t('Click or drop a file to replace')}
                                        </p>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-sm font-medium text-text">
                                            {t('Drag & drop your license document here')}
                                        </p>
                                        <p className="text-xs text-textMuted">
                                            {t('or click to browse — JPG, PNG or PDF, up to 5 MB')}
                                        </p>
                                    </>
                                )}
                                <input
                                    id="license_document"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    onChange={(e) => setFile(e.target.files?.[0])}
                                    className="sr-only"
                                    required
                                />
                            </label>
                            {errors.license_document && (
                                <p className="mt-1.5 text-sm text-danger">{errors.license_document}</p>
                            )}
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex items-center justify-center gap-2 rounded-interactive bg-primary px-6 py-2.5 text-sm font-semibold text-onPrimary shadow-resting transition-colors hover:bg-primaryHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing disabled:opacity-50"
                            >
                                {processing ? t('Submitting...') : t('Submit for review')}
                            </button>
                            <Link
                                href={route('profile.edit')}
                                className="text-sm font-semibold text-textMuted transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                            >
                                {t('Back to profile')}
                            </Link>
                        </div>
                    </form>
                ) : (
                    verification && (
                        <div className="mt-6 flex items-start gap-3 rounded-container border border-border bg-surface p-4 text-sm text-textMuted shadow-resting">
                            <ShieldAlert className="mt-0.5 h-5 w-5 flex-shrink-0 text-textMuted" aria-hidden="true" />
                            <p>
                                {verification.status === 'approved'
                                    ? t('You already have a verified verification. No further action is needed right now.')
                                    : t('You already have a pending verification. No further action is needed right now.')}
                            </p>
                        </div>
                    )
                )}
            </div>
        </PublicLayout>
    );
}
