<?php

declare(strict_types=1);

namespace Plugins\Promotions\Filament\Resources;

use App\Core\Support\HasMinimumRole;
use App\Enums\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Plugins\Promotions\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode;
use Plugins\Promotions\Filament\Resources\PromoCodeResource\Pages\EditPromoCode;
use Plugins\Promotions\Filament\Resources\PromoCodeResource\Pages\ListPromoCodes;
use Plugins\Promotions\Filament\Resources\PromoCodeResource\Schemas\PromoCodeForm;
use Plugins\Promotions\Filament\Resources\PromoCodeResource\Tables\PromoCodesTable;
use Plugins\Promotions\Models\PromoCode;

/**
 * Admin CRUD for promo/discount codes. Lives in the promotions plugin and
 * registers itself into Filament's default panel from
 * PromotionsServiceProvider::boot() — the exact same self-registration
 * pattern as the reviews and driver-verification plugins, so core's
 * AdminPanelProvider never references this plugin's namespace (Hard Rule 1).
 */
class PromoCodeResource extends Resource
{
    use HasMinimumRole;

    protected static function minimumRole(): Role
    {
        return Role::Admin;
    }

    protected static ?string $model = PromoCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Promo Codes';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return PromoCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromoCodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoCodes::route('/'),
            'create' => CreatePromoCode::route('/create'),
            'edit' => EditPromoCode::route('/{record}/edit'),
        ];
    }
}
