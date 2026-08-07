<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plugins;

use App\Core\Support\HasMinimumRole;
use App\Enums\Role;
use App\Filament\Resources\Plugins\Pages\EditPlugin;
use App\Filament\Resources\Plugins\Pages\ListPlugins;
use App\Models\Plugin;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * Admin-only enable/disable toggle for installed plugins.
 *
 * Deliberately List + Edit only — no create or delete. Plugins are
 * pre-registered in config/plugins.php (slug -> ServiceProvider) and get a
 * `plugins` DB row via PluginManager::activate(); creating arbitrary rows
 * here would register nothing, and deleting a row for an enabled plugin
 * would silently leave its provider registered for the rest of the process
 * (PluginManager only reads the DB at boot). The Edit page's toggle flips
 * is_enabled, which takes effect on the next request's boot (per the
 * helper text), matching the source e-commerce project's PluginResource.
 */
class PluginResource extends Resource
{
    use HasMinimumRole;

    protected static function minimumRole(): Role
    {
        return Role::Admin;
    }

    protected static ?string $model = Plugin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $navigationLabel = 'Plugins';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->helperText('Changes take effect on the next request.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),

                ToggleColumn::make('is_enabled')
                    ->label('Enabled'),

                TextColumn::make('updated_at')
                    ->label('Last changed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlugins::route('/'),
            'edit' => EditPlugin::route('/{record}/edit'),
        ];
    }
}
