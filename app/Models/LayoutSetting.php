<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayoutSetting extends Model
{
    protected $fillable = ['slot_name', 'active_variant_id'];
}
