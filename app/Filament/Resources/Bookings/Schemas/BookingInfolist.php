<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Booking')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('vehicle.license_plate')->label('Vehicle'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('guest_name')->label('Customer name')->placeholder('—'),
                        TextEntry::make('guest_email')->label('Customer email')->placeholder('—'),
                        TextEntry::make('user.name')->label('Registered user')->placeholder('Guest booking'),
                        TextEntry::make('pickupLocation.name')->label('Pickup location'),
                        TextEntry::make('returnLocation.name')->label('Return location'),
                        TextEntry::make('pickup_at')->dateTime(),
                        TextEntry::make('return_at')->dateTime(),
                        TextEntry::make('total_price')->money('MAD'),
                        TextEntry::make('security_deposit_amount')->label('Deposit')->money('MAD'),
                    ]),

                Section::make('Payments')
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->label('')
                            ->schema([
                                TextEntry::make('type'),
                                TextEntry::make('gateway'),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('amount')->money('MAD'),
                                TextEntry::make('provider_reference')->placeholder('—'),
                                TextEntry::make('created_at')->dateTime(),
                            ])
                            ->columns(6),
                    ]),

                Section::make('Condition / Damage Reports')
                    ->schema([
                        RepeatableEntry::make('damageReports')
                            ->label('')
                            ->schema([
                                TextEntry::make('stage')->badge(),
                                TextEntry::make('description'),
                                TextEntry::make('photo_paths')
                                    ->label('Photos')
                                    // getStateUsing() (not formatStateUsing()) — Filament treats an
                                    // empty array as "no state" and skips formatStateUsing entirely,
                                    // which silently rendered blank for a report with zero photos
                                    // instead of the intended "0 attached". Found by actually
                                    // inspecting the real rendered HTML during verification, not by
                                    // reading the code and assuming formatStateUsing would run.
                                    ->getStateUsing(function ($record): string {
                                        $paths = $record->photo_paths;
                                        $paths = is_string($paths) ? json_decode($paths, true) : $paths;

                                        return count($paths ?? []).' attached';
                                    }),
                                TextEntry::make('reportedBy.name')->label('Reported by'),
                                TextEntry::make('created_at')->dateTime(),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }
}
