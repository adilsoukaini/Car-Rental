<?php

declare(strict_types=1);

namespace Plugins\Promotions\Filament\Resources\PromoCodeResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Plugins\Promotions\Filament\Resources\PromoCodeResource;

class EditPromoCode extends EditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
