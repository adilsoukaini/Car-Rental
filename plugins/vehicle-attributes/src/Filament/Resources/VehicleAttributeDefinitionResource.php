<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Filament\Resources;

use App\Core\Support\HasMinimumRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages\CreateVehicleAttributeDefinition;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages\EditVehicleAttributeDefinition;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages\ListVehicleAttributeDefinitions;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Schemas\VehicleAttributeDefinitionForm;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Tables\VehicleAttributeDefinitionsTable;
use Plugins\VehicleAttributes\Models\VehicleAttributeDefinition;
use UnitEnum;

/**
 * CRUD for attribute definitions — the "add as many custom spec fields as
 * we want, no code per field" half of the EAV system. Registered into the
 * default panel from VehicleAttributesServiceProvider::boot() (never
 * referenced by core).
 */
class VehicleAttributeDefinitionResource extends Resource
{
    use HasMinimumRole;

    protected static ?string $model = VehicleAttributeDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Vehicle Attributes';

    protected static string|UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return VehicleAttributeDefinitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleAttributeDefinitionsTable::configure($table);
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
            'index' => ListVehicleAttributeDefinitions::route('/'),
            'create' => CreateVehicleAttributeDefinition::route('/create'),
            'edit' => EditVehicleAttributeDefinition::route('/{record}/edit'),
        ];
    }
}
