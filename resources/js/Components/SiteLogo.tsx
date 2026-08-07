import { usePage } from '@inertiajs/react';
import { Car } from 'lucide-react';

interface SiteLogoProps {
    className?: string;
    iconClassName?: string;
    textClassName?: string;
}

export default function SiteLogo({ className = '', iconClassName = '', textClassName = '' }: SiteLogoProps) {
    const { props } = usePage<{ siteIdentity?: { siteName?: string; logoUrl?: string | null } }>();
    const siteName = props.siteIdentity?.siteName ?? 'Car Rental';
    const logoUrl = props.siteIdentity?.logoUrl;

    return (
        <div className={`flex items-center gap-2 ${className}`}>
            {logoUrl ? (
                <img src={logoUrl} alt={siteName} className={`h-8 w-auto ${iconClassName}`} />
            ) : (
                <Car className={`h-7 w-7 text-primary ${iconClassName}`} aria-hidden="true" />
            )}
            <span className={`font-display text-lg font-bold text-text ${textClassName}`}>
                {siteName}
            </span>
        </div>
    );
}
