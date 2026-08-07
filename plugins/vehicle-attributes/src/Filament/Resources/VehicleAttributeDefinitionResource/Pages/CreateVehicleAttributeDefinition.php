<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource;

class CreateVehicleAttributeDefinition extends CreateRecord
{
    protected static string $resource = VehicleAttributeDefinitionResource::class;
}
