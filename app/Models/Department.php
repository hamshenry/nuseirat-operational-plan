<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'manager_name',
        'email',
        'phone',
        'entry_override_open',
        'entry_override_until',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_override_open' => 'boolean',
            'entry_override_until' => 'date',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
