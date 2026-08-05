<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property array<string, mixed> $data
 * @property bool $is_active
 */
class Theme extends Model
{
    protected $fillable = ['name', 'slug', 'data', 'is_active'];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
    ];
}
