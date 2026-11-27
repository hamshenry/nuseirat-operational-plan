<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sub_objective_id',
        'code',
        'title',
        'description',
        'sort_order',
        'deleted_by_id',
    ];

    public function subObjective(): BelongsTo
    {
        return $this->belongsTo(SubObjective::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
