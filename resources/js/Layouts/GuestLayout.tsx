import SiteLogo from '@/Components/SiteLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-background pt-6 sm:justify-center sm:pt-0">
            <div>
                <Link href="/">
                    <SiteLogo />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-surface px-6 py-4 shadow-resting sm:max-w-md sm:rounded-container">
                {children}
            </div>
        </div>
    );
}
