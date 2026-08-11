<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Vehicle;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Imports vehicles from a CSV uploaded through the admin bulk-import page.
 *
 * Column mapping (header row, slugified — "License Plate" -> "license_plate"):
 * make, model, year, category, license_plate, daily_rate, seat_count,
 * transmission_type, fuel_type, mileage, status.
 *
 * Behaviour, deliberately explicit:
 * - `status` defaults to 'available' when the column is missing/blank.
 * - `mileage` defaults to 0 when missing/blank (matches the schema default).
 * - Rows are processed one at a time (no WithBatchInserts). This is
 *   intentional: `model()` records the license plate in `$seenPlates` as soon
 *   as a row passes validation, and the next row's validation closure uses
 *   that set to reject a duplicate plate *before* the DB insert is attempted —
 *   with a batch insert, the whole batch would validate first and both rows of
 *   a within-file duplicate would pass, then the second insert would violate
 *   the unique constraint and abort the whole batch.
 * - Invalid rows are skipped, not fatal: validation failures are reported via
 *   SkipsOnFailure (each Failure carries the row number + reasons); genuine DB
 *   errors during insert are caught via SkipsOnError. The Filament page reads
 *   `failures()`, `errors()`, and the counts below to show a summary.
 *
 * Successful-insert counting is exact: `$createdCount` is incremented in
 * `model()` (i.e. the row passed validation), and `errors()` contains any
 * insert that then failed — so the page computes
 * success = createdCount - errors->count(), failures = failures()->count() + errors->count().
 */
class VehiclesImport implements SkipsEmptyRows, SkipsOnError, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;
    use SkipsErrors;
    use SkipsFailures;

    /** @var array<string, true> License plates already imported by this run (lowercased). */
    private array $seenPlates = [];

    /** @var array<string, bool>|null Existing plates preloaded once (lowercased). */
    private ?array $existingPlates = null;

    private int $createdCount = 0;

    /**
     * Normalise a raw row before validation runs against it:
     * - trim strings, blank strings become null
     * - lowercase known enum words so "SUV" / "Economy" / "Diesel" still pass
     *   the lowercase Rule::in() constraints without the admin having to match
     *   case exactly.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function prepareForValidation(array $row, int $index): array
    {
        $enumWords = ['economy', 'suv', 'luxury', 'van', 'manual', 'automatic', 'petrol', 'diesel', 'electric', 'hybrid', 'available', 'rented', 'maintenance'];

        return collect($row)->map(function ($value) {
            if (is_string($value)) {
                $value = trim($value);

                if ($value === '') {
                    return null;
                }
            }

            return $value;
        })->map(function ($value) use ($enumWords) {
            if (is_string($value) && in_array(mb_strtolower($value), $enumWords, true)) {
                return mb_strtolower($value);
            }

            return $value;
        })->toArray();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): ?Vehicle
    {
        $plate = $row['license_plate'] ?? null;

        if (is_string($plate) && $plate !== '') {
            $this->seenPlates[mb_strtolower($plate)] = true;
        }

        $this->createdCount++;

        return new Vehicle([
            'make' => $row['make'] ?? null,
            'model' => $row['model'] ?? null,
            'year' => $row['year'] ?? null,
            'category' => $row['category'] ?? null,
            'license_plate' => $plate,
            'daily_rate' => $row['daily_rate'] ?? null,
            'seat_count' => $row['seat_count'] ?? null,
            'transmission_type' => $row['transmission_type'] ?? null,
            'fuel_type' => $row['fuel_type'] ?? null,
            'mileage' => $row['mileage'] ?? 0,
            'status' => $row['status'] ?? 'available',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $yearMax = (int) date('Y') + 1;

        return [
            '*.make' => ['required', 'string', 'max:255'],
            '*.model' => ['required', 'string', 'max:255'],
            '*.year' => ['required', 'integer', 'min:1980', 'max:'.$yearMax],
            '*.category' => ['required', Rule::in(['economy', 'suv', 'luxury', 'van'])],
            '*.license_plate' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail): void {
                    $normalized = mb_strtolower(trim((string) $value));

                    if (isset($this->seenPlates[$normalized])) {
                        $fail('This license plate appears more than once in the file.');
                    } elseif (isset($this->existingPlates()[$normalized])) {
                        $fail('A vehicle with this license plate already exists.');
                    }
                },
            ],
            '*.daily_rate' => ['required', 'numeric', 'gt:0'],
            '*.seat_count' => ['required', 'integer', 'min:1', 'max:255'],
            '*.transmission_type' => ['required', Rule::in(['manual', 'automatic'])],
            '*.fuel_type' => ['required', Rule::in(['petrol', 'diesel', 'electric', 'hybrid'])],
            '*.mileage' => ['nullable', 'integer', 'min:0'],
            '*.status' => ['nullable', Rule::in(['available', 'rented', 'maintenance'])],
        ];
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    /**
     * Existing license plates in the DB, loaded once per import run
     * (rule 8 — never one query per row).
     *
     * @return array<string, bool>
     */
    private function existingPlates(): array
    {
        if ($this->existingPlates === null) {
            $this->existingPlates = Vehicle::query()
                ->pluck('license_plate')
                ->mapWithKeys(fn (string $plate) => [mb_strtolower(trim($plate)) => true])
                ->all();
        }

        return $this->existingPlates;
    }
}
