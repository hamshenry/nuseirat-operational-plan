<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityVersion extends Model
{
    protected $fillable = [
        'activity_id',
        'version_number',
        'snapshot_json',
        'change_summary',
        'changed_by_id',
        'changed_by_name',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function snapshot(): array
    {
        return json_decode($this->snapshot_json ?? '{}', true) ?: [];
    }
}
