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
export default function VehicleGallery({ images }: { images: VehicleGalleryImage[] }) {
    const [activeImage, setActiveImage] = useState(0);

    // Clamp the active index to the actual gallery size (defensive, since
    // activeImage is state) and expose the current image for the hero.
    const safeActive = Math.min(activeImage, Math.max(images.length - 1, 0));
    const currentImage = images.length > 0 ? images[safeActive] : null;

    return (
        <div className="rounded-container border border-border bg-surface p-4 shadow-resting">
            <div className="overflow-hidden rounded-container bg-background">
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
            </div>

            {images.length > 1 && (
                <div className="mt-3 flex items-center justify-center gap-2">
                    {images.map((image, index) => (
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
