import PublicLayout from '@/Layouts/PublicLayout';
import { DriverVerification } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Show({ verification }: { verification: DriverVerification | null }) {
    const canSubmitNew = verification === null || verification.status === 'rejected';

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

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('driver-verification.store'), {
            forceFormData: true,
        });
    };

    return (
        <PublicLayout>
            <Head title="Driver Verification" />

            <div className="p-8">
            <h1 className="mb-8 font-display text-3xl font-bold text-text">
                Driver Verification
            </h1>

            {verification && (
                <div className="mb-8 max-w-lg rounded-container border border-border bg-surface p-6 shadow-resting">
                    <p className="mb-2 text-sm text-textMuted">
                        Current status:{' '}
                        <span className="font-semibold text-text">{verification.status}</span>
                    </p>
                    <p className="text-sm text-textMuted">
                        License {verification.license_number} ({verification.license_country})
                    </p>
                    {verification.status === 'rejected' && verification.rejection_reason && (
                        <p className="mt-2 text-sm text-danger">
                            Rejected: {verification.rejection_reason}
                        </p>
                    )}
                </div>
            )}

            {canSubmitNew && (
                <form onSubmit={submit} className="max-w-lg space-y-4 rounded-container border border-border bg-surface p-6 shadow-resting">
                    <div>
                        <label className="mb-1 block text-sm text-textMuted">License number</label>
                        <input
                            type="text"
                            value={data.license_number}
                            onChange={(e) => setData('license_number', e.target.value)}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                            required
                        />
                        {errors.license_number && (
                            <p className="mt-1 text-sm text-danger">{errors.license_number}</p>
                        )}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm text-textMuted">License country</label>
                        <input
                            type="text"
                            value={data.license_country}
                            onChange={(e) => setData('license_country', e.target.value)}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                            required
                        />
                        {errors.license_country && (
                            <p className="mt-1 text-sm text-danger">{errors.license_country}</p>
                        )}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm text-textMuted">Date of birth</label>
                        <input
                            type="date"
                            value={data.date_of_birth}
                            onChange={(e) => setData('date_of_birth', e.target.value)}
                            className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                            required
                        />
                        {errors.date_of_birth && (
                            <p className="mt-1 text-sm text-danger">{errors.date_of_birth}</p>
                        )}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm text-textMuted">License document (jpg, png, or pdf)</label>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            onChange={(e) => setData('license_document', e.target.files?.[0] ?? null)}
                            className="w-full text-sm text-text"
                            required
                        />
                        {errors.license_document && (
                            <p className="mt-1 text-sm text-danger">{errors.license_document}</p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-interactive bg-primary px-4 py-2 font-body text-onPrimary shadow-resting hover:bg-primaryHover"
                    >
                        Submit for review
                    </button>
                </form>
            )}
            </div>
        </PublicLayout>
    );
}
