<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('address_line')
                    ->required()
                    ->maxLength(255),

                TextInput::make('city')
                    ->required()
                    ->maxLength(255),

                TextInput::make('country')
                    ->required()
                    ->maxLength(255),

                TextInput::make('latitude')
                    ->numeric()
                    ->step('0.0000001')
                    ->nullable(),

                TextInput::make('longitude')
                    ->numeric()
                    ->step('0.0000001')
                    ->nullable(),

                Toggle::make('is_active')
                    ->label('Active — offered as a pickup/return point for new bookings')
                    ->default(true),
            ]);
    }
}
