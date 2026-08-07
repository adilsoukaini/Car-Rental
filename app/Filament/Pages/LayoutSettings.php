<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Support\HasMinimumRole;
use App\Core\Support\LayoutVariantRegistry;
use App\Enums\Role;
use App\Models\LayoutSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

/**
 * Lets an Admin pick which registered layout variant is active for each
 * layout region (fleet-listing card, vehicle detail, checkout, ...) without
 * a code change. The public storefront reads the result via
 * HandleInertiaRequests' activeLayoutVariants prop + LayoutSlot.
 *
 * Only slots with at least one registered variant appear here; a region
 * with no DB row falls back to its first registered variant
 * (LayoutVariantRegistry::activeComponentFor()).
 */
class LayoutSettings extends Page
{
    use HasMinimumRole;

    protected static function minimumRole(): Role
    {
        return Role::Admin;
    }

    protected string $view = 'filament.pages.layout-settings';

    /** Holds the form field values via statePath('data'). */
    public ?array $data = [];

    public function mount(): void
    {
        $values = [];

        foreach (LayoutVariantRegistry::allRegisteredSlots() as $slot) {
            $values[$this->fieldName($slot)] = $this->activeVariantId($slot);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $fields = array_map(
            fn (string $slot) => Select::make($this->fieldName($slot))
                ->label($this->slotLabel($slot))
                ->options($this->optionsFor($slot))
                ->required(),
            LayoutVariantRegistry::allRegisteredSlots(),
        );

        return $schema
            ->statePath('data')
            ->components($fields);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (LayoutVariantRegistry::allRegisteredSlots() as $slot) {
            $field = $this->fieldName($slot);

            if (isset($data[$field])) {
                LayoutSetting::updateOrCreate(
                    ['slot_name' => $slot],
                    ['active_variant_id' => $data[$field]],
                );
            }
        }

        Notification::make()
            ->title('Layout updated')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                $this->getFormActionsContentComponent(),
            ]);
    }

    public function getFormActionsContentComponent(): Component
    {
        return Actions::make([
            Action::make('save')
                ->label('Save layout')
                ->submit('save'),
        ])->key('form-actions');
    }

    // ------------------------------------------------------------------
    // Filament v4 navigation overrides — getters rather than typed
    // properties because the base Page class declares union-typed
    // properties that a narrower ?string can't satisfy.
    // ------------------------------------------------------------------

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-swatch';
    }

    public static function getNavigationLabel(): string
    {
        return 'Layout Variants';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Convert slot name to a form-safe field key: 'vehicle-card' → 'vehicle_card'. */
    private function fieldName(string $slot): string
    {
        return str_replace('-', '_', $slot);
    }

    /** Convert slot name to a human label: 'vehicle-card' → 'Vehicle Card'. */
    private function slotLabel(string $slot): string
    {
        return ucwords(str_replace('-', ' ', $slot));
    }

    private function activeVariantId(string $slot): string
    {
        return LayoutSetting::where('slot_name', $slot)->value('active_variant_id')
            ?? LayoutVariantRegistry::availableFor($slot)[0]['variantId']
            ?? '';
    }

    /** @return array<string, string> variantId => label */
    private function optionsFor(string $slot): array
    {
        return collect(LayoutVariantRegistry::availableFor($slot))
            ->pluck('label', 'variantId')
            ->all();
    }
}
