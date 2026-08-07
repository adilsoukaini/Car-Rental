import { Link } from '@inertiajs/react';
import { Home } from 'lucide-react';

interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface BreadcrumbsProps {
    items: BreadcrumbItem[];
    className?: string;
}

/**
 * Accessible breadcrumb trail: Home (icon + link) followed by the supplied
 * items. The final item is the current page — plain text with
 * aria-current="page". Intermediate items with an href render as links. The
 * separator is a `/` glyph; every color/spacing value comes from theme tokens.
 */
export default function Breadcrumbs({ items, className = '' }: BreadcrumbsProps) {
    return (
        <nav aria-label="Breadcrumb" className={className}>
            <ol className="flex flex-wrap items-center gap-2 text-sm">
                <li>
                    <Link
                        href={route('home')}
                        className="inline-flex items-center gap-1.5 text-textMuted transition hover:text-text"
                    >
                        <Home className="h-4 w-4" aria-hidden="true" />
                        <span className="font-medium">Home</span>
                    </Link>
                </li>

                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li key={index} className="flex items-center gap-2">
                            <span className="text-textMuted" aria-hidden="true">
                                /
                            </span>

                            {item.href && !isLast ? (
                                <Link
                                    href={item.href}
                                    className="text-textMuted transition hover:text-text"
                                >
                                    {item.label}
                                </Link>
                            ) : (
                                <span
                                    className="font-medium text-text"
                                    aria-current="page"
                                >
                                    {item.label}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
