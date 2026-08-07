<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Support\HasMinimumRole;
use App\Enums\Role;
use App\Models\SiteIdentity;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Admin-only singleton settings page for the storefront site identity:
 * site name, primary logo, and favicon. Persisted to the `site_identity`
 * table (always exactly one row, id=1) and read back by
 * HandleInertiaRequests to share `siteIdentity` with the storefront.
 *
 * Uses Filament's schema form system (Schema, the v4 replacement for
 * Form) rather than a hand-rolled Blade form — FileUpload handles the
 * public-disk store + image validation, and the page's `form(Schema $schema)`
 * method is picked up automatically by InteractsWithSchemas when `$this->form`
 * is accessed (same mechanism resources use, just on a Page).
 *
 * Scope note: logo_dark_path deliberately not built (the source e-commerce
 * project has it, but this project's storefront SiteLogo doesn't consume a
 * dark variant yet) — same "don't build a second mechanism for a need that
 * doesn't exist" reasoning as LayoutVariantRegistry.
 *
 * @property-read Schema $form
 */
class SiteIdentitySettings extends Page
{
    use HasMinimumRole;

    protected static function minimumRole(): Role
    {
        return Role::Admin;
    }

    protected static ?string $title = 'Site Identity';

    protected static ?string $slug = 'site-identity';

    protected string $view = 'filament.pages.site-identity-settings';

    // ------------------------------------------------------------------
    // Filament v4 overrides — use getters rather than typed properties
    // because the base Page class declares properties with union types
    // (BackedEnum|string|null, etc.) that ?string doesn't satisfy.
    // ------------------------------------------------------------------

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-storefront';
    }

    public static function getNavigationLabel(): string
    {
        return 'Site Identity';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    // ------------------------------------------------------------------
    // Form
    // ------------------------------------------------------------------

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $record = SiteIdentity::firstOrCreate(['id' => 1], ['site_name' => 'Car Rental']);

        $this->form->fill([
            'site_name' => $record->site_name,
            'logo_path' => $record->logo_path ? [$record->logo_path] : [],
            'favicon_path' => $record->favicon_path ? [$record->favicon_path] : [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_name')
                    ->label('Site name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Used in the storefront header text fallback and browser title.'),

                FileUpload::make('logo_path')
                    ->label('Primary logo')
                    ->image()
                    ->disk('public')
                    ->directory('site-identity')
                    ->helperText('Shown on light-background themes. If none is uploaded, the site name is displayed as text.'),

                FileUpload::make('favicon_path')
                    ->label('Favicon')
                    ->disk('public')
                    ->directory('site-identity')
                    ->acceptedFileTypes(['image/x-icon', 'image/png', 'image/svg+xml'])
                    ->helperText('Displayed in browser tabs and bookmarks. Recommended: 32×32 PNG or .ico file.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $record = SiteIdentity::firstOrCreate(['id' => 1], ['site_name' => 'Car Rental']);

        $record->update([
            'site_name' => $data['site_name'],
            'logo_path' => $this->firstUpload($data['logo_path'] ?? []),
            'favicon_path' => $this->firstUpload($data['favicon_path'] ?? []),
        ]);

        Notification::make()
            ->title('Site identity updated')
            ->success()
            ->send();
    }

    // ------------------------------------------------------------------

    /**
     * A single FileUpload's state can come back as either an array (its
     * hydrated shape) or a bare string depending on how the component
     * resolved it — normalize to a single path or null.
     *
     * @param  array<int|string, string>|string|null  $upload
     */
    private function firstUpload(array|string|null $upload): ?string
    {
        if (is_string($upload) && $upload !== '') {
            return $upload;
        }

        if (is_array($upload) && count($upload) > 0) {
            return (string) array_values($upload)[0];
        }

        return null;
    }
}
