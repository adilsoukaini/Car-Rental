import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';
import { VehicleGalleryImage } from '@/types';
import { useState } from 'react';

/**
 * Single-hero vehicle gallery — the default layout variant for the
 * `vehicle-gallery` slot on the vehicle detail page
 * (resources/js/Pages/Vehicles/Show.tsx). A big aspect-video hero image with
 * pagination dots below; the dots appear only when more than one image
 * exists. This is the gallery markup extracted unchanged from Show.tsx —
 * the only behavioural difference is that the active-image state now lives
 * here instead of on the page (the page no longer needs to know which
 * gallery image is shown), and the alt-text fallback is a generic string
 * since the component doesn't receive the vehicle's make/model.
 */
export default function VehicleGallery({ images }: { images?: VehicleGalleryImage[] | null }) {
    const [activeImage, setActiveImage] = useState(0);

    // Normalise a missing/empty gallery to an empty array so the placeholder
    // renders instead of the component throwing on images.length.
    const galleryImages = Array.isArray(images) ? images : [];

    // Clamp the active index to the actual gallery size (defensive, since
    // activeImage is state) and expose the current image for the hero.
    const safeActive = Math.min(activeImage, Math.max(galleryImages.length - 1, 0));
    const currentImage = galleryImages.length > 0 ? galleryImages[safeActive] : null;

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
            </div>

            {galleryImages.length > 1 && (
                <div className="mt-3 flex items-center justify-center gap-2">
                    {galleryImages.map((image, index) => (
                        <button
                            key={index}
                            type="button"
                            aria-label={`Voir l'image ${index + 1}`}
                            onClick={() => setActiveImage(index)}
                            className={`h-2 w-2 rounded-pill transition-colors ${
                                index === activeImage ? 'bg-primary' : 'bg-border'
                            }`}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
