<?php

namespace Plugins\DriverVerification\Filament\Resources\DriverVerifications\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DriverVerificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Customer')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->required(),

                TextInput::make('license_number')
                    ->required()
                    ->maxLength(255),

                TextInput::make('license_country')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('date_of_birth')
                    ->required()
                    ->maxDate(now()->subYears(16)),

                FileUpload::make('license_document_path')
                    ->label('License document')
                    ->disk('local')
                    ->directory('driver-licenses')
                    ->required(),
            ]);
    }
}
