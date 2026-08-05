<?php

declare(strict_types=1);

namespace App\Filament\Resources\Themes\Schemas;

use App\Core\Support\ThemeSchemaRegistry;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ThemeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('URL-safe identifier, e.g. "spring-sale".'),

                FileUpload::make('json_file')
                    ->label('Theme JSON file')
                    ->required()
                    ->acceptedFileTypes(['application/json', 'text/plain'])
                    ->disk('local')
                    ->directory('theme-uploads')
                    ->helperText('Upload a JSON file with color, font, radius, and shadow fields.'),

                Placeholder::make('schema_hint')
                    ->label('Required fields')
                    ->content(static function (): string {
                        $fields = ThemeSchemaRegistry::fields();
                        $colorFields = implode(', ', array_keys(array_filter($fields, fn ($f) => $f['type'] === 'color')));
                        $stringFields = implode(', ', array_keys(array_filter($fields, fn ($f) => $f['type'] === 'string')));

                        return "{$colorFields} (hex colors) | {$stringFields} (strings)";
                    }),
            ]);
    }
}
