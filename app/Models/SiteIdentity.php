<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (id=1) holding the admin-editable site identity: site name,
 * logo, and favicon. Consumed by HandleInertiaRequests (shared `siteIdentity`
 * prop) and the SiteIdentitySettings admin page. Falls back to config when
 * the row (or a column) is null — see HandleInertiaRequests.
 *
 * @property string|null $site_name
 * @property string|null $logo_path
 * @property string|null $favicon_path
 */
class SiteIdentity extends Model
{
    protected $table = 'site_identity';

    protected $fillable = [
        'site_name',
        'logo_path',
        'favicon_path',
    ];
}
