import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';
import { VehicleGalleryImage } from '@/types';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

/**
 * Carousel layout variant for the `vehicle-gallery` slot on the vehicle
 * detail page (resources/js/Pages/Vehicles/Show.tsx). Same contract as the
 * single-hero variant ({ images }) but a different arrangement: a big
 * aspect-video hero with prev/next arrow buttons overlaid on it, and a
 * horizontally scrollable strip of thumbnails below. Clicking a thumbnail
 * (or using the arrows) changes the hero image.
 *
 * All colours are theme tokens — the arrow buttons sit over arbitrary
 * uploaded photos, so they use the photo-overlay tokens (photoScrim scrim +
 * onPhoto foreground), the same pair the photo overlay system was built
 * for. The selected thumbnail is outlined in the primary token.
 */
export default function VehicleGalleryCarousel({ images }: { images: VehicleGalleryImage[] }) {
    const [activeImage, setActiveImage] = useState(0);

    const safeActive = Math.min(activeImage, Math.max(images.length - 1, 0));
    const currentImage = images.length > 0 ? images[safeActive] : null;

    const prev = () => {
        if (images.length === 0) return;
        setActiveImage((i) => (i - 1 + images.length) % images.length);
    };

    const next = () => {
        if (images.length === 0) return;
        setActiveImage((i) => (i + 1) % images.length);
    };

    return (
        <div className="rounded-container border border-border bg-surface p-4 shadow-resting">
            <div className="relative overflow-hidden rounded-container bg-background">
                {currentImage ? (
                    <img
                        src={currentImage.url}
                        alt={currentImage.altText ?? 'Photo du véhicule'}
                        className="aspect-video w-full object-cover"
                    />
                ) : (
                    <div className="flex aspect-video w-full items-center justify-center">
                        <VehiclePlaceholderIcon />
                    </div>
                )}

                {images.length > 1 && (
                    <>
                        <button
                            type="button"
                            aria-label="Image précédente"
                            onClick={prev}
                            className="absolute left-3 top-1/2 -translate-y-1/2 rounded-pill bg-photoScrim/50 p-2 text-onPhoto transition hover:bg-photoScrim/70"
                        >
                            <ChevronLeft className="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            aria-label="Image suivante"
                            onClick={next}
                            className="absolute right-3 top-1/2 -translate-y-1/2 rounded-pill bg-photoScrim/50 p-2 text-onPhoto transition hover:bg-photoScrim/70"
                        >
                            <ChevronRight className="h-5 w-5" />
                        </button>
                    </>
                )}
            </div>

            {images.length > 1 && (
                <div className="mt-3 flex gap-2 overflow-x-auto">
                    {images.map((image, index) => (
                        <button
                            key={index}
                            type="button"
                            aria-label={`Voir l'image ${index + 1}`}
                            onClick={() => setActiveImage(index)}
                            className={`h-16 w-24 shrink-0 overflow-hidden rounded-interactive border-2 transition ${
                                index === safeActive ? 'border-primary' : 'border-border hover:border-primaryHover'
                            }`}
                        >
                            <img
                                src={image.url}
                                alt={image.altText ?? `Photo du véhicule ${index + 1}`}
                                className="h-full w-full object-cover"
                            />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
