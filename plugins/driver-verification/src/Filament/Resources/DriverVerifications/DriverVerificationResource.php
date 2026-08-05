<?php

namespace Plugins\DriverVerification\Filament\Resources\DriverVerifications;

use App\Core\Support\HasMinimumRole;
use App\Models\DriverVerification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\Pages\CreateDriverVerification;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\Pages\EditDriverVerification;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\Pages\ListDriverVerifications;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\Pages\ViewDriverVerification;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\Schemas\DriverVerificationForm;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\Schemas\DriverVerificationInfolist;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\Tables\DriverVerificationsTable;

class DriverVerificationResource extends Resource
{
    use HasMinimumRole;

    protected static ?string $model = DriverVerification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return DriverVerificationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DriverVerificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriverVerificationsTable::configure($table);
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
            'index' => ListDriverVerifications::route('/'),
            'create' => CreateDriverVerification::route('/create'),
            'view' => ViewDriverVerification::route('/{record}'),
            'edit' => EditDriverVerification::route('/{record}/edit'),
        ];
    }
}
