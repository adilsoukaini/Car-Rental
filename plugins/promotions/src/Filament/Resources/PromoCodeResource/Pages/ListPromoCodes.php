<?php

declare(strict_types=1);

namespace Plugins\Promotions\Filament\Resources\PromoCodeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Plugins\Promotions\Filament\Resources\PromoCodeResource;

class ListPromoCodes extends ListRecords
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
