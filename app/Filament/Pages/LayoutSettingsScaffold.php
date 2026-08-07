<?php

namespace App\Filament\Pages;

use App\Core\Support\LayoutVariantRegistry;
use App\Models\LayoutSetting;
use Filament\Pages\Page;

class LayoutSettingsScaffold extends Page
{
    protected static ?string $title = 'Layout Variants';

    protected static ?string $slug = 'layout-variants';

    protected string $view = 'filament.pages.layout-settings-scaffold';

    // ------------------------------------------------------------------
    // Filament v4 overrides — use getters rather than typed properties
    // because the base Page class declares properties with union types
    // (BackedEnum|string|null, etc.) that ?string doesn't satisfy.
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
    // Business logic
    // ------------------------------------------------------------------

    /** @return array<string, list<array{variantId: string, label: string, componentName: string, pluginSlug: string}>> */
    protected function getSlots(): array
    {
        $slots = [];

        foreach (LayoutVariantRegistry::allRegisteredSlots() as $slotName) {
            $slots[$slotName] = LayoutVariantRegistry::availableFor($slotName);
        }

        return $slots;
    }

    protected function getActiveVariant(string $slotName): string
    {
        return LayoutSetting::where('slot_name', $slotName)
            ->value('active_variant_id') ?? '';
    }

    public function setActiveVariant(string $slotName, string $variantId): void
    {
        LayoutSetting::updateOrCreate(
            ['slot_name' => $slotName],
            ['active_variant_id' => $variantId],
        );
    }
}
