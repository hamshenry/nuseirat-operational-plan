<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiskObstacle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'planning_year_id',
        'department_id',
        'activity_id',
        'title',
        'description',
        'category',
        'likelihood',
        'impact',
        'risk_score',
        'severity',
        'mitigation_plan',
        'owner_name',
        'status',
        'created_by_id',
        'deleted_by_id',
    ];

    public function planningYear(): BelongsTo
    {
        return $this->belongsTo(PlanningYear::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'financial' => 'مالي',
            'technical' => 'فني وتقني',
            'administrative' => 'إداري وتنظيمي',
            'external' => 'خارجي ومجتمعي',
            'legal' => 'قانوني وتشريعي',
            default => $this->category,
        };
    }

    public function severityLabel(): string
    {
        return match ($this->severity) {
            'critical' => 'حرج',
            'high' => 'مرتفع',
            'medium' => 'متوسط',
            default => 'منخفض',
        };
    }

    public function severityBadgeClass(): string
    {
        return match ($this->severity) {
            'critical' => 'badge-danger',
            'high' => 'badge-warning',
            'medium' => 'badge-info',
            default => 'badge-success',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'مفتوح',
            'mitigating' => 'قيد المعالجة',
            'resolved' => 'تم الحل',
            'escalated' => 'مصعّد للإدارة',
            default => $this->status,
        };
    }
}
