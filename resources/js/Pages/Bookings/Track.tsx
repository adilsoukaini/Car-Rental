import PublicLayout from '@/Layouts/PublicLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import Text from '@/Components/Text';
import { useTranslation } from '@/hooks/useTranslation';

const inputClass =
    'w-full rounded-interactive border border-border bg-background px-4 py-3 text-sm text-text placeholder:text-textMuted focus:border-secondary focus:ring-1 focus:ring-secondary outline-none transition-all';

/**
 * Public booking lookup — enter the booking reference + email used at booking
 * time to find your booking. Same credential model as the e-commerce project's
 * order_number lookup: the random 10-char booking_number plus a matching email
 * is what grants access, no login required.
 */
export default function Track() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        booking_number: '',
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('bookings.track.lookup'));
    };

    return (
        <PublicLayout>
            <Head title={t('Track your booking')} />

            <div className="mx-auto flex min-h-[60vh] w-full max-w-md items-center px-4 py-12 sm:px-6">
                <div className="w-full">
                    <Text variant="h1" className="mb-2">
                        {t('Find your booking')}
                    </Text>
                    <p className="mb-6 text-sm text-textMuted">
                        {t('Enter your booking reference and the email address you used to book.')}
                    </p>

                    <form onSubmit={submit} noValidate className="space-y-4 rounded-container border border-border bg-surface p-6 shadow-resting">
                        <div>
                            <label htmlFor="booking_number" className="mb-1 block text-sm font-medium text-text">
                                {t('Booking reference')}
                            </label>
                            <input
                                id="booking_number"
                                type="text"
                                value={data.booking_number}
                                onChange={(e) => setData('booking_number', e.target.value)}
                                className={inputClass}
                                placeholder="e.g. A1B2C3D4E5"
                                autoComplete="off"
                            />
                            {errors.booking_number && (
                                <p className="mt-1 text-sm text-danger">{t(errors.booking_number)}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="email" className="mb-1 block text-sm font-medium text-text">
                                {t('Email')}
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className={inputClass}
                                placeholder="you@example.com"
                                autoComplete="email"
                            />
                            {errors.email && (
                                <p className="mt-1 text-sm text-danger">{errors.email}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-interactive bg-primary px-4 py-3 text-sm font-semibold text-onPrimary transition active:scale-[0.98] disabled:opacity-50"
                        >
                            {processing ? t('Searching...') : t('Find my booking')}
                        </button>
                    </form>
                </div>
            </div>
        </PublicLayout>
    );
}
