/**
 * The correct empty-state fallback for a vehicle with no uploaded photo —
 * not a permanent stand-in for missing functionality (see the vehicle-media
 * plugin, which lets staff actually upload real photos). Shared by every
 * vehicle card layout variant.
 */
interface VehiclePlaceholderIconProps {
    /** Optional size/color overrides. When omitted, defaults to the 12×12 icon size. */
    className?: string;
}

export default function VehiclePlaceholderIcon({ className }: VehiclePlaceholderIconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            className={`text-textMuted ${className ?? 'h-12 w-12'}`}
            fill="none"
            stroke="currentColor"
            strokeWidth={1.5}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M3 13.5 4.5 9a2 2 0 0 1 1.9-1.5h11.2A2 2 0 0 1 19.5 9L21 13.5M3 13.5v4a1 1 0 0 0 1 1h1.5a1 1 0 0 0 1-1v-1h11v1a1 1 0 0 0 1 1H20a1 1 0 0 0 1-1v-4M3 13.5h18M7 16.5h.01M17 16.5h.01"
            />
        </svg>
    );
}
