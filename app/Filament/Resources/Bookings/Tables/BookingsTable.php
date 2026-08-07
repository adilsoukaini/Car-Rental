<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deliberately no create/edit/delete actions here — see BookingResource's
 * docblock. Bookings and their financial records are view-only from the
 * admin panel; the only mutations available are the explicit deposit
 * actions on the View page.
 */
class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->searchable(),
                TextColumn::make('vehicle.license_plate')->label('Vehicle')->searchable(),
                TextColumn::make('guest_name')
                    ->label('Customer')
                    ->searchable(query: function (Builder $query, string $search): void {
                        // Searches both guest_name (guests) and user.name
                        // (registered customers) — matching the Customer
                        // column's getStateUsing display logic.
                        $query->where('guest_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $uq) => $uq->where('name', 'like', "%{$search}%"));
                    })
                    ->getStateUsing(function ($record) {
                        if (! $record instanceof Booking) {
                            return null;
                        }

                        $user = $record->user;

                        return $user !== null ? $user->name : $record->guest_name;
                    }),
                TextColumn::make('pickup_at')->dateTime(),
                TextColumn::make('return_at')->dateTime(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'primary' => 'checked_out',
                        'gray' => 'returned',
                        'danger' => 'cancelled',
                    ]),
                TextColumn::make('total_price')->money('MAD'),
                TextColumn::make('security_deposit_amount')->label('Deposit')->money('MAD'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'checked_out' => 'Checked Out',
                        'returned' => 'Returned',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(function (Action $action): string {
                        $livewire = $action->getLivewire();

                        return route('filament.admin.bookings.export', array_filter([
                            'status' => $livewire?->getTableFilterFormState('status')['value'] ?? null,
                            'search' => $livewire?->getTableSearch(),
                        ]));
                    })
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
