<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleAttributeDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(100)
                ->helperText('Display label shown to customers, e.g. "GPS".'),

            TextInput::make('key')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(60)
                ->helperText('Machine key, e.g. "gps" — used by the vehicle detail page.'),

            Select::make('type')
                ->options([
                    'text' => 'Text',
                    'number' => 'Number',
                    'textarea' => 'Textarea',
                    'boolean' => 'Boolean (Yes/No)',
                    'select' => 'Select (dropdown)',
                ])
                ->default('text')
                ->required()
                ->live(),

            KeyValue::make('options')
                ->keyLabel('Value (stored)')
                ->valueLabel('Label (displayed)')
                ->helperText('For "Select" type only: stored value → displayed label pairs.')
                ->visible(fn ($get): bool => $get('type') === 'select'),

            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->minValue(0),
        ]);
    }
}
