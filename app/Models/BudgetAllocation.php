<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetAllocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'planning_year_id',
        'department_id',
        'allocated_amount',
        'self_funded',
        'grants_funded',
        'external_funded',
        'notes',
        'updated_by_id',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'self_funded' => 'decimal:2',
            'grants_funded' => 'decimal:2',
            'external_funded' => 'decimal:2',
        ];
    }

    public function planningYear(): BelongsTo
    {
        return $this->belongsTo(PlanningYear::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
