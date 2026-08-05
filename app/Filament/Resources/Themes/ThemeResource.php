<?php

declare(strict_types=1);

namespace App\Filament\Resources\Themes;

use App\Core\Support\HasMinimumRole;
use App\Core\Support\ThemeSchemaRegistry;
use App\Enums\Role;
use App\Filament\Resources\Themes\Pages\CreateTheme;
use App\Filament\Resources\Themes\Pages\ListThemes;
use App\Filament\Resources\Themes\Schemas\ThemeForm;
use App\Filament\Resources\Themes\Tables\ThemesTable;
use App\Models\Theme;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ThemeResource extends Resource
{
    use HasMinimumRole;

    protected static function minimumRole(): Role
    {
        return Role::Admin;
    }

    protected static ?string $model = Theme::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return ThemeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ThemesTable::configure($table);
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
            'index' => ListThemes::route('/'),
            'create' => CreateTheme::route('/create'),
        ];
    }

    /**
     * Parse the uploaded JSON, validate schema, and return the data array.
     * Throws on failure so CreateTheme can surface the error via Filament notifications.
     *
     * @return array<string, mixed>
     */
    public static function parseAndValidateUpload(string $path): array
    {
        $json = Storage::disk('local')->get($path);

        if ($json === null) {
            throw new \RuntimeException('Uploaded file could not be read.');
        }

        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new \InvalidArgumentException('File is not valid JSON.');
        }

        $result = ThemeSchemaRegistry::validate($data);

        if (! $result->valid) {
            throw new \InvalidArgumentException(
                'Schema validation failed: '.implode(' | ', $result->errors)
            );
        }

        return $data;
    }
}
