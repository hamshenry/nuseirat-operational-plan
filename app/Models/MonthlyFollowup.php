<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonthlyFollowup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'activity_id',
        'planning_year_id',
        'month',
        'planned_percent',
        'actual_percent',
        'achieved_value',
        'actual_cost',
        'execution_status',
        'achievements_note',
        'obstacles_note',
        'next_steps',
        'updated_by_id',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'planned_percent' => 'decimal:2',
            'actual_percent' => 'decimal:2',
            'actual_cost' => 'decimal:2',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function executionStatusLabel(): string
    {
        return match ($this->execution_status) {
            'completed' => 'مكتمل',
            'in_progress' => 'قيد التنفيذ',
            'delayed' => 'متأخر',
            'blocked' => 'متعثر',
            default => 'لم يبدأ',
        };
    }

    public function executionBadgeClass(): string
    {
        return match ($this->execution_status) {
            'completed' => 'badge-success',
            'in_progress' => 'badge-info',
            'delayed' => 'badge-warning',
            'blocked' => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    public static function monthName(int $month): string
    {
        return [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ][$month] ?? (string) $month;
    }
}
