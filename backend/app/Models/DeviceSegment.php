<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_installation_id',
        'segment_type',
        'segment_value',
    ];

    /**
     * @return BelongsTo<DeviceInstallation, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(DeviceInstallation::class, 'device_installation_id');
    }
}
