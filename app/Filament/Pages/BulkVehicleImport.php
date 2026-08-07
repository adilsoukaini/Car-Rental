<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Support\HasMinimumRole;
use App\Enums\Role;
use App\Imports\VehiclesImport;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Excel as ExcelReader;
use Maatwebsite\Excel\Validators\Failure;

/**
 * Admin-only page that bulk-imports vehicles from a CSV uploaded through the
 * admin panel. Pure Blade view (no Filament form) — the flow is:
 *
 *   1. Upload a CSV -> preview the first 5 parsed rows immediately.
 *   2. Download a template CSV with the exact expected headers (or fill one
 *      manually).
 *   3. Click Import -> VehiclesImport runs, skipping invalid rows.
 *   4. A success/failure summary (with per-row reasons) is shown inline and
 *      via a Filament notification.
 *
 * The CSV columns and their validation live entirely in VehiclesImport; this
 * page is only the upload/preview/report shell around it.
 *
 * @property-read string $view
 */
class BulkVehicleImport extends Page
{
    use HasMinimumRole;
    use WithFileUploads;

    protected static function minimumRole(): Role
    {
        return Role::Admin;
    }

    protected static ?string $title = 'Import Vehicles';

    protected static ?string $slug = 'bulk-vehicle-import';

    protected string $view = 'filament.pages.bulk-vehicle-import';

    // ------------------------------------------------------------------
    // Filament v4 overrides — use getters rather than typed properties
    // because the base Page class declares properties with union types
    // (BackedEnum|string|null, etc.) that ?string doesn't satisfy.
    // ------------------------------------------------------------------

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-arrow-up';
    }

    public static function getNavigationLabel(): string
    {
        return 'Import Vehicles';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    // ------------------------------------------------------------------
    // Livewire state
    // ------------------------------------------------------------------

    /** @var TemporaryUploadedFile|null */
    public $csvFile = null;

    /** @var array<int, array<string, mixed>>|null First 5 parsed data rows. */
    public ?array $preview = null;

    public ?string $previewError = null;

    public ?int $importedCount = null;

    public ?int $failedCount = null;

    /** @var array<int, array{row: int|null, errors: list<string>, values: array<string, mixed>}>|null */
    public ?array $failureRows = null;

    // ------------------------------------------------------------------
    // Upload / preview
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected function csvRules(): array
    {
        return [
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function updatedCsvFile(): void
    {
        $this->validate($this->csvRules());

        $this->resetResults();

        try {
            // toArray() is grouped per sheet -> [sheet => [row, row, ...]].
            $sheets = (new VehiclesImport)->toArray($this->csvFile->getRealPath(), null, ExcelReader::CSV);
            $this->preview = array_slice($sheets[0] ?? [], 0, 5);
            $this->previewError = null;
        } catch (\Throwable $e) {
            $this->preview = null;
            $this->previewError = 'Could not read the CSV file: '.$e->getMessage();
        }
    }

    // ------------------------------------------------------------------
    // Import
    // ------------------------------------------------------------------

    public function import(): void
    {
        $this->validate($this->csvRules());

        $import = new VehiclesImport;
        $import->import($this->csvFile->getRealPath(), null, ExcelReader::CSV);

        $errorCount = $import->errors()->count();
        $this->importedCount = max(0, $import->getCreatedCount() - $errorCount);
        $this->failedCount = $import->failures()->count() + $errorCount;

        // Group validation failures by spreadsheet row so the admin sees
        // "Row 7: [reason, reason]" rather than a flat list of field errors.
        $this->failureRows = collect($import->failures())
            ->groupBy(fn (Failure $failure): int => $failure->row())
            ->map(fn ($group, $row): array => [
                'row' => (int) $row,
                'errors' => $group->flatMap(fn (Failure $failure): array => $failure->errors())->unique()->values()->all(),
                'values' => (array) $group->first()->values(),
            ])
            ->values()
            ->all();

        foreach ($import->errors() as $error) {
            $this->failureRows[] = [
                'row' => null,
                'errors' => ['Database error: '.$error->getMessage()],
                'values' => [],
            ];
        }

        Notification::make()
            ->title(sprintf('Imported %d vehicle(s)', $this->importedCount))
            ->body($this->failedCount > 0 ? sprintf('%d row(s) skipped', $this->failedCount) : 'All rows imported successfully.')
            ->success()
            ->send();
    }

    // ------------------------------------------------------------------

    private function resetResults(): void
    {
        $this->importedCount = null;
        $this->failedCount = null;
        $this->failureRows = null;
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! auth()->user()?->hasAtLeast(Role::Admin)) {
            abort(403);
        }

        $headers = ['make', 'model', 'year', 'category', 'license_plate', 'daily_rate', 'seat_count', 'transmission_type', 'fuel_type', 'mileage', 'status'];

        return response()->streamDownload(function () use ($headers) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, $headers);
            fputcsv($fh, ['Toyota', 'Corolla', '2024', 'economy', 'ABC-123', '350', '5', 'automatic', 'petrol', '0', 'available']);
            fclose($fh);
        }, 'vehicle-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
