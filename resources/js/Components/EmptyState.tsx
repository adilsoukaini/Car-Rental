interface EmptyStateProps {
    icon?: React.ReactNode;
    title: string;
    description?: string;
    action?: React.ReactNode;
    className?: string;
}

/**
 * Centered empty-state placeholder: optional icon, a display title, an
 * optional muted description, and an optional action (e.g. a button).
 */
export default function EmptyState({
    icon,
    title,
    description,
    action,
    className = '',
}: EmptyStateProps) {
    return (
        <div
            className={`flex flex-col items-center justify-center gap-3 py-12 text-center ${className}`}
        >
            {icon && <div className="text-textMuted">{icon}</div>}
            <h3 className="font-display text-lg font-bold text-text">{title}</h3>
            {description && (
                <p className="max-w-sm text-sm text-textMuted">{description}</p>
            )}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
