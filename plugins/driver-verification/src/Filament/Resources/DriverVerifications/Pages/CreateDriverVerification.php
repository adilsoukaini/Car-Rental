<?php

namespace Plugins\DriverVerification\Filament\Resources\DriverVerifications\Pages;

use Filament\Resources\Pages\CreateRecord;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\DriverVerificationResource;

class CreateDriverVerification extends CreateRecord
{
    protected static string $resource = DriverVerificationResource::class;
}
