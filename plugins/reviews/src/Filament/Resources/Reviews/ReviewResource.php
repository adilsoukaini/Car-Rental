<?php

declare(strict_types=1);

namespace Plugins\Reviews\Filament\Resources\Reviews;

use App\Core\Support\HasMinimumRole;
use App\Enums\Role;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Plugins\Reviews\Filament\Resources\Reviews\Pages\ListReviews;
use Plugins\Reviews\Filament\Resources\Reviews\Tables\ReviewsTable;

/**
 * List-only, matching BookingResource/DriverVerificationResource's shape
 * for staff-moderated data — the table's own actions (Approve/Reject) are
 * the only mutations, not a create/edit form.
 */
class ReviewResource extends Resource
{
    use HasMinimumRole;

    protected static function minimumRole(): Role
    {
        return Role::Staff;
    }

    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }
}
