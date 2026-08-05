<?php

namespace Plugins\DriverVerification\Filament\Resources\DriverVerifications\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\DriverVerificationResource;

class ListDriverVerifications extends ListRecords
{
    protected static string $resource = DriverVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
