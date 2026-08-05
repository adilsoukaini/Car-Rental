<?php

declare(strict_types=1);

namespace Plugins\Reviews\Filament\Resources\Reviews\Pages;

use Filament\Resources\Pages\ListRecords;
use Plugins\Reviews\Filament\Resources\Reviews\ReviewResource;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;
}
