<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Support\HasMinimumRole;
use App\Core\Support\VehicleFilterRegistry;
use App\Core\Support\VehicleSortRegistry;
use App\Enums\Role;
use App\Models\CatalogControlSetting;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Admin-only page controlling which registered fleet-listing filters and sorts
 * are active on the storefront. Every registered VehicleFilterRegistry filter
 * and VehicleSortRegistry sort gets an enable toggle; the storefront
 * (VehicleController -> /vehicles) only exposes and applies enabled controls.
 *
 * Absence of a catalog_control_settings row means "enabled" (the pre-admin-
 * control default), so a fresh install behaves exactly as before. On save the
 * page upserts a row per control and clears the registries' shared enabled-map
 * cache so the very next storefront request sees the new state (Hard Rule 11).
 *
 * @property-read Schema $form
 */
class CatalogControlSettings extends Page
{
    use HasMinimumRole;

    protected static function minimumRole(): Role
    {
        return Role::Admin;
    }

    protected static ?string $title = 'Fleet Filters';

    protected static ?string $slug = 'catalog-controls';

    protected string $view = 'filament.pages.catalog-control-settings';

    // ------------------------------------------------------------------
    // Filament v4 overrides — use getters rather than typed properties
    // because the base Page class declares properties with union types
    // (BackedEnum|string|null, etc.) that ?string doesn't satisfy.
    // ------------------------------------------------------------------

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-funnel';
    }

    public static function getNavigationLabel(): string
    {
        return 'Fleet Filters';
    }

    public static function getNavigationSort(): ?int
    {
        return 25;
    }

    // ------------------------------------------------------------------
    // Form
    // ------------------------------------------------------------------

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $values = [];

        foreach (VehicleFilterRegistry::all() as $filter) {
            $values[$this->filterField($filter->id())] = CatalogControlSetting::isControlEnabled(
                CatalogControlSetting::TYPE_FILTER,
                $filter->id(),
            );
        }

        foreach (VehicleSortRegistry::all() as $sort) {
            $values[$this->sortField($sort->id())] = CatalogControlSetting::isControlEnabled(
                CatalogControlSetting::TYPE_SORT,
                $sort->id(),
            );
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $fields = [];

        foreach (VehicleFilterRegistry::all() as $filter) {
            $fields[] = Toggle::make($this->filterField($filter->id()))
                ->label("Filter — {$filter->label()}")
                ->helperText("Registry ID: {$filter->id()}. When off, this filter is hidden from the storefront FilterBar and not applied to /vehicles requests.")
                ->default(true);
        }

        foreach (VehicleSortRegistry::all() as $sort) {
            $fields[] = Toggle::make($this->sortField($sort->id()))
                ->label("Sort — {$sort->label()}")
                ->helperText("Registry ID: {$sort->id()}. When off, this sort is hidden from the storefront dropdown and never applied.")
                ->default(true);
        }

        return $schema
            ->statePath('data')
            ->components($fields);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $filterOrder = 0;
        foreach (VehicleFilterRegistry::all() as $filter) {
            CatalogControlSetting::updateOrCreate(
                ['control_type' => CatalogControlSetting::TYPE_FILTER, 'control_id' => $filter->id()],
                [
                    'is_enabled' => (bool) ($data[$this->filterField($filter->id())] ?? true),
                    'sort_order' => $filterOrder++,
                ],
            );
        }

        $sortOrder = 0;
        foreach (VehicleSortRegistry::all() as $sort) {
            CatalogControlSetting::updateOrCreate(
                ['control_type' => CatalogControlSetting::TYPE_SORT, 'control_id' => $sort->id()],
                [
                    'is_enabled' => (bool) ($data[$this->sortField($sort->id())] ?? true),
                    'sort_order' => $sortOrder++,
                ],
            );
        }

        // The registries cache the enabled map per process — clear it so the
        // very next storefront request sees the new state (Hard Rule 11).
        CatalogControlSetting::resetCache();

        Notification::make()
            ->title('Fleet filters updated')
            ->success()
            ->send();
    }

    // ------------------------------------------------------------------
    // Helpers — registry IDs become form-safe field names.
    // ------------------------------------------------------------------

    private function filterField(string $id): string
    {
        return 'filter_'.$id;
    }

    private function sortField(string $id): string
    {
        return 'sort_'.$id;
    }
}
