<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource;

class EditVehicleAttributeDefinition extends EditRecord
{
    protected static string $resource = VehicleAttributeDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
