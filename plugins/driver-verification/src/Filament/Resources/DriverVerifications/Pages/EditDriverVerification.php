<?php

namespace Plugins\DriverVerification\Filament\Resources\DriverVerifications\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\DriverVerificationResource;

class EditDriverVerification extends EditRecord
{
    protected static string $resource = DriverVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
