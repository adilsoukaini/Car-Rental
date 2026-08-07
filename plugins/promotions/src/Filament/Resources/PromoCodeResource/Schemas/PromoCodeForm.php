<?php

declare(strict_types=1);

namespace Plugins\Promotions\Filament\Resources\PromoCodeResource\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PromoCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->helperText('Customers enter this at checkout. Stored and matched case-insensitively.'),

                Select::make('type')
                    ->required()
                    ->options([
                        'percentage' => 'Percentage off',
                        'fixed' => 'Fixed amount off',
                    ])
                    ->helperText('Determines how the "Value" field is interpreted.'),

                TextInput::make('value')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Percentage: 10 = 10% off. Fixed: 100 = 100.00 MAD off.'),

                TextInput::make('min_booking_amount')
                    ->label('Minimum booking amount (MAD)')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->helperText('Minimum booking subtotal required (MAD). Leave blank for no minimum.'),

                TextInput::make('max_uses')
                    ->label('Usage limit')
                    ->numeric()
                    ->minValue(1)
                    ->nullable()
                    ->helperText('Leave blank for unlimited uses.'),

                TextInput::make('uses_count')
                    ->label('Times used')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Auto-incremented when a booking using this code is confirmed.'),

                DateTimePicker::make('expires_at')
                    ->label('Expires at')
                    ->nullable()
                    ->helperText('Leave blank for no expiry.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
