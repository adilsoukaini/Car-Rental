import { LayoutSlot } from '@/layoutComponentRegistry';
import { Vehicle } from '@/types';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useRef, useState } from 'react';

/**
 * Horizontal scrollable carousel of vehicle cards — admin-switchable
 * alternative to the static grid. Renders via Home.tsx's featured section
 * when `homepageFeatured` is set to `carousel` in Layout Variants.
 *
 * Every color/spacing/radius comes from theme tokens (Hard Rule 3).
 */
export default function VehicleCarousel({ vehicles }: { vehicles: Vehicle[] }) {
    const scrollRef = useRef<HTMLDivElement>(null);
    const [canScrollLeft, setCanScrollLeft] = useState(false);
    const [canScrollRight, setCanScrollRight] = useState(true);

    const updateScrollState = () => {
        const el = scrollRef.current;
        if (!el) return;
        setCanScrollLeft(el.scrollLeft > 0);
        setCanScrollRight(el.scrollLeft + el.clientWidth < el.scrollWidth - 4);
    };

    const scroll = (direction: 'left' | 'right') => {
        const el = scrollRef.current;
        if (!el) return;
        const amount = el.clientWidth * 0.75;
        el.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
        setTimeout(updateScrollState, 350);
    };

    if (!vehicles || vehicles.length === 0) return null;

    return (
        <div className="relative">
            {canScrollLeft && (
                <button
                    onClick={() => scroll('left')}
                    className="absolute -left-3 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-surface text-text shadow-raised transition hover:bg-background"
                    aria-label="Scroll left"
                >
                    <ChevronLeft className="h-5 w-5" />
                </button>
            )}
            <div
                ref={scrollRef}
                onScroll={updateScrollState}
                className="flex gap-6 overflow-x-auto scroll-smooth pb-2"
                style={{ scrollSnapType: 'x mandatory', scrollbarWidth: 'none' }}
            >
                {vehicles.map((v) => (
                    <div key={v.id} className="w-72 flex-shrink-0" style={{ scrollSnapAlign: 'start' }}>
                        <LayoutSlot name="vehicleCard" vehicle={v} />
                    </div>
                ))}
            </div>
            {canScrollRight && (
                <button
                    onClick={() => scroll('right')}
                    className="absolute -right-3 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-surface text-text shadow-raised transition hover:bg-background"
                    aria-label="Scroll right"
                >
                    <ChevronRight className="h-5 w-5" />
                </button>
            )}
        </div>
    );
}
