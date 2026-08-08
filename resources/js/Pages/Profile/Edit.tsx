import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DriverVerificationCard from '@/Components/DriverVerificationCard';
import { SlotOutlet } from '@/pluginComponentRegistry';
import { DriverVerification, PageProps } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

interface SlotEntry {
    component: string;
    props: Record<string, unknown>;
}

export default function Edit({
    mustVerifyEmail,
    status,
    dashboardWidgets,
    driverVerification,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    dashboardWidgets: SlotEntry[];
    driverVerification: DriverVerification | null;
}) {
    // Shared prop authored in HandleInertiaRequests — the authority for the
    // status string (never null here: the profile page is auth-gated, so the
    // table check has already passed or the middleware degraded to null).
    const { driverVerificationStatus } = usePage<PageProps>().props;
    const { t } = useTranslation();

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-text">
                    {t('Profile')}
                </h2>
            }
        >
            <Head title={t('Profile')} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {dashboardWidgets.length > 0 && (
                        <SlotOutlet slot={dashboardWidgets} />
                    )}

                    <DriverVerificationCard
                        status={driverVerificationStatus}
                        verification={driverVerification}
                    />

                    <div className="rounded-container bg-surface p-4 shadow-resting sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className="rounded-container bg-surface p-4 shadow-resting sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    <div className="rounded-container bg-surface p-4 shadow-resting sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
