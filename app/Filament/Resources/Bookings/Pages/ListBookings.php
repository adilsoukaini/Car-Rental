<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    /**
     * Eager-load the user relation so the Customer column's getStateUsing
     * (which reads $record->user) doesn't fire an N+1 query per row.
     */
    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with('user');
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
