import PublicLayout from '@/Layouts/PublicLayout';
import Text from '@/Components/Text';
import { Head } from '@inertiajs/react';

/**
 * Privacy Policy — a static informational page required by Google Play's
 * "Privacy policy URL" store-listing field (and good practice regardless).
 * Written in English to match the Play Store default language (en-GB); the
 * storefront default locale is French, but this is a legal/compliance document
 * keyed to the store listing, not a marketing page. All colors/spacing via
 * theme tokens (Hard Rule 3).
 *
 * Content must stay in sync with the data the app actually collects — driver
 * verification (Phase 9), bookings, payments via Stripe, and notifications.
 */

interface Section {
    title: string;
    paragraphs?: string[];
    bullets?: string[];
}

const sections: Section[] = [
    {
        title: '1. Information we collect',
        paragraphs: ['When you use the Driveway Morocco app or website, we collect:'],
        bullets: [
            'Account information — your name and email address when you register.',
            'Booking details — pickup and return locations, dates, and the vehicle you choose.',
            'Driver verification — driving licence details when required before a vehicle handover.',
            'Payment information — processed securely by Stripe; we do not store your full card number.',
            'Device information — for app functionality and notifications.',
        ],
    },
    {
        title: '2. How we use your information',
        paragraphs: ['We use your information to:'],
        bullets: [
            'Process and manage your bookings.',
            'Send booking confirmations and updates.',
            'Process payments and security deposits.',
            'Verify drivers before handover.',
            'Provide customer support.',
            'Improve our services.',
        ],
    },
    {
        title: '3. Sharing your information',
        paragraphs: [
            'We do not sell your personal information. We share data only with:',
        ],
        bullets: [
            'Stripe — our payment processor.',
            'Service providers necessary to operate the app and website.',
        ],
    },
    {
        title: '4. Data security',
        paragraphs: [
            'We use industry-standard encryption (HTTPS/TLS) and secure storage to protect your data. Payment details are handled entirely by Stripe and are not stored on our servers.',
        ],
    },
    {
        title: '5. Data retention',
        paragraphs: [
            'We retain booking and account data for as long as your account is active, or as required by law.',
        ],
    },
    {
        title: '6. Your rights',
        paragraphs: [
            'You may request access to, correction of, or deletion of your personal data at any time by contacting us at the email below.',
        ],
    },
    {
        title: '7. Contact',
        paragraphs: [
            'For privacy questions, contact us at:',
        ],
        bullets: [
            'Email: support@drivewaymorocco.com',
            'Website: https://drivewaymorocco.com',
        ],
    },
];

export default function PrivacyPolicy() {
    return (
        <PublicLayout>
            <Head title="Privacy Policy" />

            <div className="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
                <Text variant="h1">Privacy Policy</Text>
                <Text variant="body-sm" className="mt-2 text-textMuted">
                    Last updated: August 2026
                </Text>

                <div className="mt-10 space-y-10">
                    {sections.map((section) => (
                        <section key={section.title}>
                            <Text variant="h2">{section.title}</Text>
                            {section.paragraphs?.map((paragraph) => (
                                <Text
                                    key={paragraph}
                                    variant="body-base"
                                    className="mt-3 text-textMuted"
                                >
                                    {paragraph}
                                </Text>
                            ))}
                            {section.bullets && (
                                <ul className="mt-3 space-y-2">
                                    {section.bullets.map((item) => (
                                        <li
                                            key={item}
                                            className="flex items-start gap-2 text-base text-text"
                                        >
                                            <span
                                                className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-secondary"
                                                aria-hidden="true"
                                            />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>
            </div>
        </PublicLayout>
    );
}
