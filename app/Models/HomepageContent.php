<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (id=1) holding the admin-editable homepage hero/content copy:
 * hero headline/subtitle/CTA, features section heading/subtitle, and the CTA
 * band heading/subtitle. Consumed by the `/` route (shared `homepageContent`
 * prop) and the HomepageContentSettings admin page. A null column falls back
 * to the hardcoded storefront defaults in Home.tsx — see that component.
 *
 * @property string $hero_title
 * @property string|null $hero_subtitle
 * @property string|null $hero_cta_text
 * @property string|null $hero_cta_link
 * @property string|null $features_title
 * @property string|null $features_subtitle
 * @property string|null $cta_band_title
 * @property string|null $cta_band_subtitle
 */
class HomepageContent extends Model
{
    protected $table = 'homepage_content';

    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'hero_cta_text',
        'hero_cta_link',
        'features_title',
        'features_subtitle',
        'cta_band_title',
        'cta_band_subtitle',
    ];

    /**
     * Defaults matching the storefront Home.tsx copy exactly, so a fresh
     * install (or a null column) renders identically to the pre-database
     * hardcoded homepage.
     *
     * @var array<string, string|null>
     */
    public const DEFAULTS = [
        'hero_title' => "L'excellence de la location de voitures.",
        'hero_subtitle' => 'Découvrez une flotte premium pour des voyages sans compromis. Réservation rapide, véhicules impeccables, service irréprochable.',
        'hero_cta_text' => 'Trouver un véhicule',
        'hero_cta_link' => '/vehicles',
        'features_title' => 'Pourquoi nous choisir ?',
        'features_subtitle' => 'Nous redéfinissons la location de voitures avec des processus simplifiés et une flotte soigneusement entretenue.',
        'cta_band_title' => "Prêt pour l'aventure ?",
        'cta_band_subtitle' => 'Réservez dès maintenant et profitez du Maroc en toute liberté.',
    ];

    /**
     * Return the singleton homepage-content row, creating it with the
     * storefront defaults if it doesn't exist yet.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], static::DEFAULTS);
    }
}
