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
export default function VehicleGalleryCarousel({ images }: { images?: VehicleGalleryImage[] | null }) {
    const [activeImage, setActiveImage] = useState(0);

    // Normalise a missing/empty gallery to an empty array so the placeholder
    // renders instead of the component throwing on images.length.
    const galleryImages = Array.isArray(images) ? images : [];

    const safeActive = Math.min(activeImage, Math.max(galleryImages.length - 1, 0));
    const currentImage = galleryImages.length > 0 ? galleryImages[safeActive] : null;

    const prev = () => {
        if (galleryImages.length === 0) return;
        setActiveImage((i) => (i - 1 + galleryImages.length) % galleryImages.length);
    };

    const next = () => {
        if (galleryImages.length === 0) return;
        setActiveImage((i) => (i + 1) % galleryImages.length);
    };

    return (
        <div className="rounded-container border border-border bg-surface p-4 shadow-resting">
            <div className="relative aspect-[4/3] w-full overflow-hidden rounded-container bg-surface">
                {currentImage ? (
                    <img
                        src={currentImage.url}
                        alt={currentImage.altText ?? 'Photo du véhicule'}
                        loading="lazy"
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <VehiclePlaceholderIcon />
                    </div>
                )}

                {galleryImages.length > 1 && (
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

            {galleryImages.length > 1 && (
                <div className="mt-3 flex gap-2 overflow-x-auto">
                    {galleryImages.map((image, index) => (
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
                                loading="lazy"
                                className="h-full w-full object-cover"
                            />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
