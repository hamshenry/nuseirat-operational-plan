<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'operational_plan_id',
        'planning_year_id',
        'department_id',
        'main_objective_id',
        'sub_objective_id',
        'program_id',
        'activity_code',
        'seq_number',
        'title',
        'kpi_name',
        'kpi_target',
        'kpi_target_numeric',
        'verification_means',
        'responsible_entity',
        'support_type',
        'support_internal',
        'support_external',
        'q1',
        'q2',
        'q3',
        'q4',
        'priority',
        'estimated_cost',
        'funding_source',
        'budget_item',
        'status',
        'review_note',
        'reviewed_by_id',
        'reviewed_at',
        'current_version',
        'created_by_id',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'q1' => 'boolean',
            'q2' => 'boolean',
            'q3' => 'boolean',
            'q4' => 'boolean',
            'estimated_cost' => 'decimal:2',
            'kpi_target_numeric' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function operationalPlan(): BelongsTo
    {
        return $this->belongsTo(OperationalPlan::class);
    }

    public function planningYear(): BelongsTo
    {
        return $this->belongsTo(PlanningYear::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function mainObjective(): BelongsTo
    {
        return $this->belongsTo(MainObjective::class);
    }

    public function subObjective(): BelongsTo
    {
        return $this->belongsTo(SubObjective::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ActivityVersion::class)->orderByDesc('version_number');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(MonthlyFollowup::class)->orderBy('month');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderByDesc('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ActivityComment::class)->orderBy('created_at');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(RiskObstacle::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'معتمد',
            'pending' => 'قيد المراجعة',
            'rejected' => 'مرفوض',
            default => 'مسودة',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'approved' => 'badge-success',
            'pending' => 'badge-warning',
            'rejected' => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'critical' => 'عاجل ومهم',
            'high' => 'مرتفعة',
            'medium' => 'متوسطة',
            'low' => 'منخفضة',
            default => 'متوسطة',
        };
    }

    public function priorityBadgeClass(): string
    {
        return match ($this->priority) {
            'critical' => 'badge-danger',
            'high' => 'badge-warning',
            'medium' => 'badge-info',
            'low' => 'badge-secondary',
            default => 'badge-info',
        };
    }

    public function supportTypeLabel(): string
    {
        return match ($this->support_type) {
            'internal' => 'داخلي',
            'external' => 'خارجي',
            'both' => 'داخلي وخارجي',
            default => 'لا يوجد',
        };
    }

    public function quartersSummary(): string
    {
        $qs = [];
        if ($this->q1) $qs[] = 'ر1';
        if ($this->q2) $qs[] = 'ر2';
        if ($this->q3) $qs[] = 'ر3';
        if ($this->q4) $qs[] = 'ر4';
        return empty($qs) ? '—' : implode('، ', $qs);
    }

    public function latestProgress(): float
    {
        $latest = $this->followups->sortByDesc('month')->first();
        return $latest ? (float) $latest->actual_percent : 0.0;
    }

    public function totalActualSpent(): float
    {
        return (float) $this->followups->sum('actual_cost');
    }
}
