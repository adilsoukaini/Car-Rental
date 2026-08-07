/**
 * Simple loading skeleton placeholder.
 *
 * Default: `animate-pulse rounded-interactive bg-border/30` plus whatever
 * sizing className the caller passes. Common shapes are available as
 * subcomponents (Skeleton.Text / Title / Card / Avatar) — note Avatar uses
 * `!rounded-full` (important) so its full rounding deterministically beats
 * the base `rounded-interactive`, which Tailwind would otherwise emit later
 * in the stylesheet since it's a theme-extended radius key.
 */
interface SkeletonProps {
    className?: string;
}

function Skeleton({ className = '' }: SkeletonProps) {
    return (
        <div
            aria-hidden="true"
            className={`animate-pulse rounded-interactive bg-border/30 ${className}`}
        />
    );
}

export default Object.assign(Skeleton, {
    Text: ({ className = '' }: SkeletonProps) => (
        <Skeleton className={`h-4 w-full ${className}`} />
    ),
    Title: ({ className = '' }: SkeletonProps) => (
        <Skeleton className={`h-6 w-3/4 ${className}`} />
    ),
    Card: ({ className = '' }: SkeletonProps) => (
        <Skeleton className={`h-48 w-full ${className}`} />
    ),
    Avatar: ({ className = '' }: SkeletonProps) => (
        <Skeleton className={`h-10 w-10 !rounded-full ${className}`} />
    ),
});
