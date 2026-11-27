<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'planning_year_id',
        'department_id',
        'title',
        'status',
        'notes',
        'submitted_at',
        'approved_at',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'معتمدة',
            'submitted' => 'قيد المراجعة',
            'returned' => 'معادة للتعديل',
            default => 'مسودة',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'approved' => 'badge-success',
            'submitted' => 'badge-warning',
            'returned' => 'badge-danger',
            default => 'badge-secondary',
        };
    }
}
