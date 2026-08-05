<?php

declare(strict_types=1);

namespace App\Filament\Resources\Themes\Tables;

use App\Core\Support\ContrastChecker;
use App\Core\Support\ThemeManager;
use App\Models\Theme;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ThemesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('slug')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('data')
                    ->label('Preview')
                    ->getStateUsing(static function (Theme $record): string {
                        $c = $record->data['color'] ?? [];
                        $f = $record->data['font'] ?? [];
                        $r = $record->data['radius'] ?? [];

                        $primary = e($c['primary'] ?? '#000');
                        $onPrimary = e($c['onPrimary'] ?? '#fff');
                        $secondary = e($c['secondary'] ?? '#000');
                        $onSecondary = e($c['onSecondary'] ?? '#fff');
                        $bg = e($c['background'] ?? '#fff');
                        $surface = e($c['surface'] ?? '#fff');
                        $text = e($c['text'] ?? '#000');
                        $border = e($c['border'] ?? '#ccc');
                        $radius = e($r['interactive'] ?? '8px');
                        $font = e($f['display'] ?? 'sans-serif');

                        $swatch = static fn (string $color, string $label): string => "<span title=\"{$label}: {$color}\" style=\""
                            .'display:inline-block;width:20px;height:20px;'
                            ."background:{$color};border-radius:3px;"
                            .'border:1px solid rgba(0,0,0,0.12);vertical-align:middle;'
                            .'margin-right:3px;"></span>';

                        $swatches = $swatch($primary, 'primary')
                            .$swatch($secondary, 'secondary')
                            .$swatch($bg, 'background')
                            .$swatch($surface, 'surface');

                        $button = '<span style="'
                            .'display:inline-block;padding:4px 12px;'
                            ."background:{$primary};color:{$onPrimary};"
                            ."border-radius:{$radius};font-family:{$font};"
                            .'font-size:12px;font-weight:600;vertical-align:middle;'
                            .'margin-left:8px;">'
                            .'Book now'
                            .'</span>';

                        $secButton = '<span style="'
                            .'display:inline-block;padding:4px 12px;'
                            ."background:{$secondary};color:{$onSecondary};"
                            ."border-radius:{$radius};font-family:{$font};"
                            .'font-size:12px;font-weight:600;vertical-align:middle;'
                            .'margin-left:4px;">'
                            .'Deal'
                            .'</span>';

                        $card = '<span style="'
                            .'display:inline-block;padding:6px 10px;'
                            ."background:{$surface};color:{$text};"
                            ."border:1px solid {$border};border-radius:{$radius};"
                            ."font-family:{$font};font-size:11px;vertical-align:middle;"
                            .'margin-left:8px;">'
                            .'Vehicle card sample'
                            .'</span>';

                        return '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:4px;">'
                            .$swatches.$button.$secButton.$card
                            .'</div>';
                    })
                    ->html(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activate theme')
                    ->modalDescription(static function (Theme $record): string {
                        $merged = array_replace_recursive(ThemeManager::defaultData(), $record->data);
                        $failures = ContrastChecker::checkTheme($merged);

                        if (empty($failures)) {
                            return "Activate \"{$record->name}\"? All contrast checks pass.";
                        }

                        $list = implode("\n", $failures);

                        return "⚠️ Contrast warnings for \"{$record->name}\":\n{$list}\n\nActivate anyway?";
                    })
                    ->action(static function (Theme $record): void {
                        ThemeManager::activate($record->id);

                        Notification::make()
                            ->title("\"{$record->name}\" is now the active theme")
                            ->success()
                            ->send();
                    })
                    ->hidden(fn (Theme $record): bool => $record->is_active),

                Action::make('active_badge')
                    ->label('Active')
                    ->icon('heroicon-o-check-circle')
                    ->color('gray')
                    ->disabled()
                    ->visible(fn (Theme $record): bool => $record->is_active),
            ]);
    }
}
