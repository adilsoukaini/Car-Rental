<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('make')
                    ->required()
                    ->maxLength(255),

                TextInput::make('model')
                    ->required()
                    ->maxLength(255),

                TextInput::make('year')
                    ->required()
                    ->numeric()
                    ->minValue(1980)
                    ->maxValue((int) date('Y') + 1),

                Select::make('category')
                    ->options([
                        'economy' => 'Economy',
                        'suv' => 'SUV',
                        'luxury' => 'Luxury',
                        'van' => 'Van',
                    ])
                    ->required(),

                TextInput::make('license_plate')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('daily_rate')
                    ->label('Daily Rate (MAD)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),

                TextInput::make('seat_count')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(255),

                Select::make('transmission_type')
                    ->options([
                        'manual' => 'Manual',
                        'automatic' => 'Automatic',
                    ])
                    ->required(),

                Select::make('fuel_type')
                    ->options([
                        'petrol' => 'Petrol',
                        'diesel' => 'Diesel',
                        'electric' => 'Electric',
                        'hybrid' => 'Hybrid',
                    ])
                    ->required(),

                TextInput::make('mileage')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),

                Select::make('status')
                    ->options([
                        'available' => 'Available',
                        'rented' => 'Rented',
                        'maintenance' => 'Maintenance',
                    ])
                    ->default('available')
                    ->required(),

                Select::make('location_id')
                    ->label('Home Location')
                    ->options(fn () => Location::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->nullable(),
            ]);
    }
}
