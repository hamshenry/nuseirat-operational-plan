<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanningYear extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'year',
        'title',
        'is_active',
        'is_entry_open',
        'entry_start_date',
        'entry_end_date',
        'notes',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
            'is_entry_open' => 'boolean',
            'entry_start_date' => 'date',
            'entry_end_date' => 'date',
        ];
    }

    public function mainObjectives(): HasMany
    {
        return $this->hasMany(MainObjective::class)->orderBy('sort_order')->orderBy('id');
    }

    public function operationalPlans(): HasMany
    {
        return $this->hasMany(OperationalPlan::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function budgetAllocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(RiskObstacle::class);
    }
}
