<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_uuid',
        'platform',
        'app_version',
        'locale',
        'push_token',
        'push_provider',
        'notifications_enabled',
        'last_seen_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return HasMany<DeviceSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(DeviceSegment::class, 'device_installation_id');
    }
}
