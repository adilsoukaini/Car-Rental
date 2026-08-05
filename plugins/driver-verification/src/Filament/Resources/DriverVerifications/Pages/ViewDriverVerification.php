<?php

namespace Plugins\DriverVerification\Filament\Resources\DriverVerifications\Pages;

use App\Core\Events\DriverVerified;
use App\Models\DriverVerification;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\DriverVerificationResource;
use RuntimeException;

/**
 * Approve/Reject here is the ONLY place App\Core\Events\DriverVerified
 * actually gets dispatched — without it, that event (reserved since
 * Phase 1/2) has no real caller in production, same "modeled but never
 * consumed" pattern already caught twice elsewhere in this project.
 */
class ViewDriverVerification extends ViewRecord
{
    protected static string $resource = DriverVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->approveAction(),
            $this->rejectAction(),
        ];
    }

    private function verification(): DriverVerification
    {
        $record = $this->getRecord();

        if (! $record instanceof DriverVerification) {
            throw new RuntimeException('Expected a DriverVerification record.');
        }

        return $record;
    }

    private function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn () => $this->verification()->status === 'pending')
            ->action(function () {
                $verification = $this->verification();

                $verification->update([
                    'status' => 'approved',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ]);

                $user = $verification->fresh()?->user;

                if ($user instanceof User) {
                    event(new DriverVerified($user));
                }

                Notification::make()->title('Driver verification approved')->success()->send();
            });
    }

    private function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('rejection_reason')->required(),
            ])
            ->visible(fn () => $this->verification()->status === 'pending')
            ->action(function (array $data) {
                $this->verification()->update([
                    'status' => 'rejected',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'rejection_reason' => $data['rejection_reason'],
                ]);

                Notification::make()->title('Driver verification rejected')->success()->send();
            });
    }
}
