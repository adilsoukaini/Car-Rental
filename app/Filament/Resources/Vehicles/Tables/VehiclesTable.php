<?php

namespace App\Filament\Resources\Vehicles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('make')->searchable(),
                TextColumn::make('model')->searchable(),
                TextColumn::make('year'),
                BadgeColumn::make('category'),
                TextColumn::make('license_plate')->label('Plate')->searchable(),
                TextColumn::make('daily_rate')->label('Daily Rate')->money('MAD'),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'available',
                        'warning' => 'rented',
                        'danger' => 'maintenance',
                    ]),
                TextColumn::make('location.name')->label('Location')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'economy' => 'Economy',
                        'suv' => 'SUV',
                        'luxury' => 'Luxury',
                        'van' => 'Van',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'rented' => 'Rented',
                        'maintenance' => 'Maintenance',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
