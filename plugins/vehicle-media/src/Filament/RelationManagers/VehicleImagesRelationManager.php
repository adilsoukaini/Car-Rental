<?php

declare(strict_types=1);

namespace Plugins\VehicleMedia\Filament\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Plugins\VehicleMedia\Models\VehicleImage;

/**
 * Ported from the source e-commerce project's ProductImagesRelationManager
 * (Filament v3 shape there — Form/Table static methods) adapted to this
 * project's real v4 API, confirmed via `artisan make:filament-relation-
 * manager` rather than guessed (Schema replaces Form, recordActions()
 * replaces actions() — same lesson as every other Filament port in this
 * project: don't copy resource code verbatim across major versions).
 */
class VehicleImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Images';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Image')
                ->image()
                ->disk('public')
                ->directory('vehicle-images')
                ->required()
                ->columnSpanFull(),

            TextInput::make('alt_text')
                ->label('Alt text')
                ->maxLength(255)
                ->placeholder('Describe the image for accessibility'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('path')
                    ->label('Preview')
                    ->disk('public')
                    ->square()
                    ->size(64),

                TextColumn::make('alt_text')
                    ->label('Alt text')
                    ->placeholder('—')
                    ->limit(40),

                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('set_primary')
                    ->label('Set primary')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->hidden(fn (VehicleImage $record): bool => $record->is_primary)
                    ->action(function (VehicleImage $record): void {
                        $record->makePrimary();

                        Notification::make()
                            ->title('Primary image updated')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->reorderRecordsTriggerAction(
                fn (Action $action) => $action->button()->label('Reorder'),
            );
    }
}
