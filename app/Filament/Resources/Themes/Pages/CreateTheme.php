<?php

declare(strict_types=1);

namespace App\Filament\Resources\Themes\Pages;

use App\Core\Support\ContrastChecker;
use App\Core\Support\ThemeManager;
use App\Filament\Resources\Themes\ThemeResource;
use App\Models\Theme;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CreateTheme extends CreateRecord
{
    protected static string $resource = ThemeResource::class;

    /**
     * Before persisting: swap the temporary file path stored in json_file
     * for the actual parsed theme data, and run contrast checks.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $path = $data['json_file'] ?? null;

        if (! is_string($path)) {
            $this->halt();
            Notification::make()->title('No JSON file was uploaded.')->danger()->send();

            return $data;
        }

        try {
            $themeData = ThemeResource::parseAndValidateUpload($path);
        } catch (\Exception $e) {
            $this->halt();
            Notification::make()->title('Invalid theme file')->body($e->getMessage())->danger()->send();

            return $data;
        } finally {
            Storage::disk('local')->delete($path);
        }

        $merged = array_replace_recursive(ThemeManager::defaultData(), $themeData);
        $failures = ContrastChecker::checkTheme($merged);

        if (! empty($failures)) {
            Notification::make()
                ->title('Contrast warnings — theme saved but not activated')
                ->body(implode("\n", $failures))
                ->warning()
                ->persistent()
                ->send();
        }

        unset($data['json_file']);
        $data['data'] = $themeData;
        $data['is_active'] = false;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * A newly uploaded theme is never active (CreateTheme forces
     * is_active=false), so it cannot change the resolved active theme — but
     * forget the cached value anyway to keep any future path that flips the
     * row active from serving stale data for up to an hour.
     */
    protected function afterCreate(): void
    {
        Cache::forget('active_theme');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        /** @var Theme $record */
        $record = $this->record;

        return "Theme \"{$record->name}\" uploaded successfully — activate it from the list.";
    }
}
