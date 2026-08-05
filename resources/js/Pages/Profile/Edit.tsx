import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { SlotOutlet } from '@/pluginComponentRegistry';
import { Head } from '@inertiajs/react';
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
}: {
    mustVerifyEmail: boolean;
    status?: string;
    dashboardWidgets: SlotEntry[];
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-text">
                    Profile
                </h2>
            }
        >
            <Head title="Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {dashboardWidgets.length > 0 && (
                        <SlotOutlet slot={dashboardWidgets} />
                    )}

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
