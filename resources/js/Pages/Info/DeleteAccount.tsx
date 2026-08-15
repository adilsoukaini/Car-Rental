import PublicLayout from '@/Layouts/PublicLayout';
import Text from '@/Components/Text';
import { Head } from '@inertiajs/react';

/**
 * Account deletion instructions — required by Google Play's "Delete account
 * URL" data-safety field (apps that let users create an account must provide
 * both an in-app deletion option and a public web link describing how to
 * request deletion). Written in English to match the Play Store default
 * language, same as the Privacy Policy page.
 */

const sections = [
    {
        title: 'How to delete your account',
        bullets: [
            'In the app or website: sign in, open your Profile, and choose "Delete Account".',
            'By email: send a request to support@drivewaymorocco.com from the address associated with your account. We will process it within 30 days.',
        ],
    },
    {
        title: 'What is deleted',
        bullets: [
            'Your account (name, email address, phone number and password).',
            'Your booking history and any stored personal details.',
            'Driver-verification documents, including your uploaded driving licence.',
        ],
    },
    {
        title: 'What may be retained',
        bullets: [
            'Records we are legally required to keep (for example, invoicing and accounting records) for the period required by applicable law.',
            'Anonymous, aggregate data that cannot identify you.',
        ],
    },
];

export default function DeleteAccount() {
    return (
        <PublicLayout>
            <Head title="Delete Your Account" />

            <div className="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
                <Text variant="h1">Delete Your Account</Text>
                <Text variant="body-sm" className="mt-2 text-textMuted">
                    Driveway Morocco — account and data deletion
                </Text>

                <div className="mt-10 space-y-10">
                    {sections.map((section) => (
                        <section key={section.title}>
                            <Text variant="h2">{section.title}</Text>
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
                        </section>
                    ))}
                </div>
            </div>
        </PublicLayout>
    );
}
