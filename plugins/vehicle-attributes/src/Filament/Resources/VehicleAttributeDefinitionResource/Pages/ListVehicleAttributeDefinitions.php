<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource;

class ListVehicleAttributeDefinitions extends ListRecords
{
    protected static string $resource = VehicleAttributeDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
