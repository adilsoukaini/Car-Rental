<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Support\HasMinimumRole;
use App\Enums\Role;
use App\Models\HomepageContent;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Admin-only singleton settings page for the storefront homepage hero and
 * content copy: hero headline/subtitle/CTA, features section heading/subtitle,
 * and the CTA band heading/subtitle. Persisted to the `homepage_content`
 * table (always exactly one row, id=1) and read back by the `/` route to
 * share `homepageContent` with the storefront Home page.
 *
 * Same shape as SiteIdentitySettings — a Filament Page with a schema form
 * (Schema, the v4 replacement for Form), filled in mount() and upserted in
 * save().
 *
 * @property-read Schema $form
 */
class HomepageContentSettings extends Page
{
    use HasMinimumRole;

    protected static function minimumRole(): Role
    {
        return Role::Admin;
    }

    protected static ?string $title = 'Homepage Content';

    protected static ?string $slug = 'homepage-content';

    protected string $view = 'filament.pages.homepage-content-settings';

    // ------------------------------------------------------------------
    // Filament v4 overrides — use getters rather than typed properties
    // because the base Page class declares properties with union types
    // (BackedEnum|string|null, etc.) that ?string doesn't satisfy.
    // ------------------------------------------------------------------

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-home';
    }

    public static function getNavigationLabel(): string
    {
        return 'Homepage Content';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    // ------------------------------------------------------------------
    // Form
    // ------------------------------------------------------------------

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $record = HomepageContent::current();

        $this->form->fill([
            'hero_title' => $record->hero_title,
            'hero_subtitle' => $record->hero_subtitle,
            'hero_cta_text' => $record->hero_cta_text,
            'hero_cta_link' => $record->hero_cta_link,
            'features_title' => $record->features_title,
            'features_subtitle' => $record->features_subtitle,
            'cta_band_title' => $record->cta_band_title,
            'cta_band_subtitle' => $record->cta_band_subtitle,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('hero_title')
                    ->label('Hero title')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The homepage headline, shown in the hero section.'),

                Textarea::make('hero_subtitle')
                    ->label('Hero subtitle')
                    ->rows(3)
                    ->helperText('The short paragraph under the hero headline.'),

                TextInput::make('hero_cta_text')
                    ->label('Hero CTA text')
                    ->maxLength(255)
                    ->helperText('The main booking-card CTA button text (currently "Trouver un véhicule").'),

                TextInput::make('hero_cta_link')
                    ->label('Hero CTA link')
                    ->maxLength(255)
                    ->helperText('The route path the hero CTA points to, e.g. /vehicles.'),

                TextInput::make('features_title')
                    ->label('Features title')
                    ->maxLength(255)
                    ->helperText('Heading of the "Pourquoi choisir..." value-props section.'),

                Textarea::make('features_subtitle')
                    ->label('Features subtitle')
                    ->rows(3)
                    ->helperText('Subtitle under the features heading.'),

                TextInput::make('cta_band_title')
                    ->label('CTA band title')
                    ->maxLength(255)
                    ->helperText('Heading of the closing CTA band.'),

                Textarea::make('cta_band_subtitle')
                    ->label('CTA band subtitle')
                    ->rows(3)
                    ->helperText('Subtitle of the closing CTA band.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $record = HomepageContent::current();

        $record->update([
            'hero_title' => $data['hero_title'],
            'hero_subtitle' => $data['hero_subtitle'] ?? null,
            'hero_cta_text' => $data['hero_cta_text'] ?? null,
            'hero_cta_link' => $data['hero_cta_link'] ?? null,
            'features_title' => $data['features_title'] ?? null,
            'features_subtitle' => $data['features_subtitle'] ?? null,
            'cta_band_title' => $data['cta_band_title'] ?? null,
            'cta_band_subtitle' => $data['cta_band_subtitle'] ?? null,
        ]);

        Notification::make()
            ->title('Homepage content updated')
            ->success()
            ->send();
    }
}
