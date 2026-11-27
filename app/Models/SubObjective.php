<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubObjective extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'main_objective_id',
        'code',
        'title',
        'description',
        'sort_order',
        'deleted_by_id',
    ];

    public function mainObjective(): BelongsTo
    {
        return $this->belongsTo(MainObjective::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
