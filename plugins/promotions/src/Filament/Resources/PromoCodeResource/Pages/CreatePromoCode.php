<?php

declare(strict_types=1);

namespace Plugins\Promotions\Filament\Resources\PromoCodeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Plugins\Promotions\Filament\Resources\PromoCodeResource;

class CreatePromoCode extends CreateRecord
{
    protected static string $resource = PromoCodeResource::class;
}
