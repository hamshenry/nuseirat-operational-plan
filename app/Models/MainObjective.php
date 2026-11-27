<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MainObjective extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'planning_year_id',
        'code',
        'title',
        'description',
        'sort_order',
        'deleted_by_id',
    ];

    public function planningYear(): BelongsTo
    {
        return $this->belongsTo(PlanningYear::class);
    }

    public function subObjectives(): HasMany
    {
        return $this->hasMany(SubObjective::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
