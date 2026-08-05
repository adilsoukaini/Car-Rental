<?php

namespace Plugins\DriverVerification\Filament\Resources\DriverVerifications\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DriverVerificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Driver Verification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')->label('Customer'),
                        TextEntry::make('user.email')->label('Email'),
                        TextEntry::make('license_number'),
                        TextEntry::make('license_country'),
                        TextEntry::make('date_of_birth')->date(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('reviewer.name')->label('Reviewed by')->placeholder('—'),
                        TextEntry::make('reviewed_at')->dateTime()->placeholder('—'),
                        TextEntry::make('rejection_reason')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
