<?php

declare(strict_types=1);

namespace Plugins\Promotions\Filament\Resources\PromoCodeResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Plugins\Promotions\Models\PromoCode;

class PromoCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                BadgeColumn::make('type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => 'Percentage off',
                        'fixed' => 'Fixed amount off',
                        default => $state,
                    })
                    ->colors(['primary' => fn () => true]),

                TextColumn::make('value')
                    ->formatStateUsing(function ($state, PromoCode $record): string {
                        return $record->type === 'percentage'
                            ? "{$state}%"
                            : number_format((float) $state, 2).' MAD';
                    }),

                TextColumn::make('uses_count')
                    ->label('Uses')
                    ->formatStateUsing(fn (int $state, PromoCode $record): string => $record->max_uses !== null
                        ? "{$state} / {$record->max_uses}"
                        : (string) $state
                    )
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
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
