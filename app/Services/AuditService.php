<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityVersion;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\OperationalPlan;
use App\Models\PlanningYear;
use App\Models\Program;
use App\Models\SystemNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        string $entityLabel = '',
        array $details = [],
        ?User $user = null
    ): AuditLog {
        $actor = $user ?? Auth::user();
        $ip = request() ? (request()->ip() ?? '127.0.0.1') : 'cli';

        return AuditLog::create([
            'user_id' => $actor?->id,
            'username' => $actor?->username ?? 'system',
            'full_name' => $actor?->name ?? 'النظام',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => mb_substr($entityLabel, 0, 290),
            'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip_address' => mb_substr($ip, 0, 64),
        ]);
    }

    public static function notifyUser(
        int $userId,
        string $title,
        string $message,
        string $category = 'system',
        ?string $linkUrl = null
    ): SystemNotification {
        return SystemNotification::create([
            'user_id' => $userId,
            'title' => mb_substr($title, 0, 215),
            'message' => $message,
            'category' => $category,
            'link_url' => $linkUrl,
            'is_read' => false,
        ]);
    }

    public static function notifyAdmins(
        string $title,
        string $message,
        string $category = 'submission',
        ?string $linkUrl = null,
        ?int $excludeUserId = null
    ): void {
        $admins = User::where('role', 'admin')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            if ($excludeUserId && $admin->id === $excludeUserId) {
                continue;
            }
            self::notifyUser($admin->id, $title, $message, $category, $linkUrl);
        }
    }

    public static function notifyDepartment(
        int $departmentId,
        string $title,
        string $message,
        string $category = 'system',
        ?string $linkUrl = null,
        ?int $excludeUserId = null
    ): void {
        $users = User::where('department_id', $departmentId)->where('is_active', true)->get();
        foreach ($users as $u) {
            if ($excludeUserId && $u->id === $excludeUserId) {
                continue;
            }
            self::notifyUser($u->id, $title, $message, $category, $linkUrl);
        }
    }

    public static function parseNumeric(?string $raw): ?float
    {
        if (!$raw) {
            return null;
        }
        $normalized = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $raw
        );
        if (preg_match('/(\d+(?:\.\d+)?)/', $normalized, $m)) {
            return (float) $m[1];
        }
        return null;
    }

    /**
     * Automatic hierarchical activity numbering: <ProgramCode>-<Seq2Digits>
     */
    public static function generateActivityCode(int $programId, int $yearId): array
    {
        $program = Program::find($programId);
        $progCode = $program?->code ?? '0.0.0';

        $maxSeq = Activity::withTrashed()
            ->where('program_id', $programId)
            ->where('planning_year_id', $yearId)
            ->max('seq_number') ?? 0;

        $nextSeq = ((int) $maxSeq) + 1;
        $code = sprintf('%s-%02d', $progCode, $nextSeq);

        return [$code, $nextSeq];
    }

    public static function renumberYearActivities(int $yearId): int
    {
        $activities = Activity::with('program')
            ->where('planning_year_id', $yearId)
            ->orderBy('program_id')
            ->orderBy('id')
            ->get();

        $counters = [];
        $updated = 0;

        foreach ($activities as $act) {
            $pid = $act->program_id;
            $counters[$pid] = ($counters[$pid] ?? 0) + 1;
            $seq = $counters[$pid];
            $code = sprintf('%s-%02d', $act->program?->code ?? '0.0.0', $seq);
            if ($act->activity_code !== $code || (int) $act->seq_number !== $seq) {
                $act->update([
                    'activity_code' => $code,
                    'seq_number' => $seq,
                ]);
                $updated++;
            }
        }

        return $updated;
    }

    public static function activitySnapshot(Activity $activity): array
    {
        $activity->loadMissing(['mainObjective', 'subObjective', 'program', 'department']);
        return [
            'activity_code' => $activity->activity_code,
            'department_name' => $activity->department?->name ?? '',
            'main_objective_id' => $activity->main_objective_id,
            'main_objective_title' => $activity->mainObjective?->title ?? '',
            'sub_objective_id' => $activity->sub_objective_id,
            'sub_objective_title' => $activity->subObjective?->title ?? '',
            'program_id' => $activity->program_id,
            'program_title' => $activity->program?->title ?? '',
            'title' => $activity->title,
            'kpi_name' => $activity->kpi_name,
            'kpi_target' => $activity->kpi_target,
            'verification_means' => $activity->verification_means,
            'responsible_entity' => $activity->responsible_entity,
            'support_type' => $activity->support_type,
            'support_internal' => $activity->support_internal ?? '',
            'support_external' => $activity->support_external ?? '',
            'q1' => (bool) $activity->q1,
            'q2' => (bool) $activity->q2,
            'q3' => (bool) $activity->q3,
            'q4' => (bool) $activity->q4,
            'priority' => $activity->priority,
            'estimated_cost' => (float) $activity->estimated_cost,
            'funding_source' => $activity->funding_source,
            'budget_item' => $activity->budget_item ?? '',
            'status' => $activity->status,
            'review_note' => $activity->review_note ?? '',
        ];
    }

    public static function saveActivityVersion(Activity $activity, string $summary = 'تحديث النشاط', ?User $user = null): ActivityVersion
    {
        $actor = $user ?? Auth::user();
        return ActivityVersion::create([
            'activity_id' => $activity->id,
            'version_number' => (int) ($activity->current_version ?: 1),
            'snapshot_json' => json_encode(self::activitySnapshot($activity), JSON_UNESCAPED_UNICODE),
            'change_summary' => mb_substr($summary, 0, 295),
            'changed_by_id' => $actor?->id,
            'changed_by_name' => $actor?->name ?? 'النظام',
        ]);
    }

    public static function diffSnapshots(array $oldSnap, array $newSnap): array
    {
        $labels = [
            'activity_code' => 'رقم النشاط',
            'main_objective_title' => 'الهدف الرئيسي',
            'sub_objective_title' => 'الهدف الفرعي',
            'program_title' => 'البرنامج',
            'title' => 'النشاط',
            'kpi_name' => 'مؤشر الأداء',
            'kpi_target' => 'قيمة المؤشر',
            'verification_means' => 'وسائل التحقق',
            'responsible_entity' => 'الجهة المسئولة',
            'support_type' => 'نوع الجهة المساندة',
            'support_internal' => 'الجهة المساندة (داخلي)',
            'support_external' => 'الجهة المساندة (خارجي)',
            'q1' => 'الربع الأول (ر1)',
            'q2' => 'الربع الثاني (ر2)',
            'q3' => 'الربع الثالث (ر3)',
            'q4' => 'الربع الرابع (ر4)',
            'priority' => 'الأهمية',
            'estimated_cost' => 'التكلفة التقديرية',
            'funding_source' => 'مصدر التمويل',
            'budget_item' => 'البند المالي',
            'status' => 'الحالة',
            'review_note' => 'ملاحظة الاعتماد/الرفض',
        ];

        $format = function (string $key, $val): string {
            if (in_array($key, ['q1', 'q2', 'q3', 'q4'], true)) {
                return $val ? 'نعم ✓' : 'لا';
            }
            if ($key === 'estimated_cost') {
                return number_format((float) $val, 2);
            }
            if ($key === 'priority') {
                return match ($val) {
                    'critical' => 'عاجل ومهم', 'high' => 'مرتفعة', 'medium' => 'متوسطة', 'low' => 'منخفضة', default => (string) $val,
                };
            }
            if ($key === 'status') {
                return match ($val) {
                    'approved' => 'معتمد', 'pending' => 'قيد المراجعة', 'rejected' => 'مرفوض', 'draft' => 'مسودة', default => (string) $val,
                };
            }
            if ($key === 'support_type') {
                return match ($val) {
                    'internal' => 'داخلي', 'external' => 'خارجي', 'both' => 'داخلي وخارجي', 'none' => 'لا يوجد', default => (string) $val,
                };
            }
            return ($val === null || $val === '') ? '—' : (string) $val;
        };

        $rows = [];
        foreach ($labels as $field => $label) {
            $oldVal = $format($field, $oldSnap[$field] ?? null);
            $newVal = $format($field, $newSnap[$field] ?? null);
            $rows[] = [
                'field' => $field,
                'label' => $label,
                'old' => $oldVal,
                'new' => $newVal,
                'changed' => $oldVal !== $newVal,
            ];
        }

        return $rows;
    }

    public static function syncPlanStatus(int $planId): void
    {
        $plan = OperationalPlan::find($planId);
        if (!$plan) {
            return;
        }
        $statuses = Activity::where('operational_plan_id', $planId)->pluck('status')->unique()->values()->all();
        if (empty($statuses)) {
            $plan->update(['status' => 'draft']);
            return;
        }
        if ($statuses === ['approved']) {
            $plan->update([
                'status' => 'approved',
                'approved_at' => $plan->approved_at ?? now(),
            ]);
        } elseif (in_array('rejected', $statuses, true)) {
            $plan->update(['status' => 'returned']);
        } elseif (in_array('pending', $statuses, true)) {
            $plan->update([
                'status' => 'submitted',
                'submitted_at' => $plan->submitted_at ?? now(),
            ]);
        } else {
            $plan->update(['status' => 'draft']);
        }
    }

    public static function isEntryOpenForDepartment(int $yearId, ?int $departmentId): array
    {
        $year = PlanningYear::find($yearId);
        if (!$year) {
            return [false, 'السنة التخطيطية غير موجودة.'];
        }
        $today = Carbon::today();

        if ($departmentId) {
            $dept = Department::find($departmentId);
            if ($dept && $dept->entry_override_open !== null) {
                if ($dept->entry_override_open) {
                    if ($dept->entry_override_until && $today->gt($dept->entry_override_until)) {
                        return [false, 'انتهت فترة التمديد الاستثنائية للدائرة بتاريخ ' . $dept->entry_override_until->format('Y-m-d')];
                    }
                    return [true, 'فترة الإدخال مفتوحة (استثناء مخصص للدائرة).'];
                }
                return [false, 'تم إغلاق فترة الإدخال لهذه الدائرة من قبل المشرف العام.'];
            }
        }

        if (!$year->is_entry_open) {
            return [false, "فترة إدخال الخطط للعام {$year->year} مغلقة حالياً من قبل المشرف العام."];
        }
        if ($year->entry_start_date && $today->lt($year->entry_start_date)) {
            return [false, 'لم تبدأ فترة الإدخال بعد (تبدأ بتاريخ ' . $year->entry_start_date->format('Y-m-d') . ').'];
        }
        if ($year->entry_end_date && $today->gt($year->entry_end_date)) {
            return [false, 'انتهت فترة الإدخال للعام ' . $year->year . ' بتاريخ ' . $year->entry_end_date->format('Y-m-d') . '.'];
        }

        return [true, "فترة إدخال الخطط للعام {$year->year} مفتوحة حالياً."];
    }

    public static function calcRiskSeverity(int $likelihood, int $impact): array
    {
        $l = max(1, min(5, $likelihood));
        $i = max(1, min(5, $impact));
        $score = $l * $i;

        $severity = match (true) {
            $score >= 16 => 'critical',
            $score >= 10 => 'high',
            $score >= 5 => 'medium',
            default => 'low',
        };

        return [$score, $severity];
    }

    public static function getSelectedYear(?int $requestedYearId = null): PlanningYear
    {
        if ($requestedYearId) {
            $found = PlanningYear::find($requestedYearId);
            if ($found) {
                session(['selected_year_id' => $found->id]);
                return $found;
            }
        }

        $sessYearId = session('selected_year_id');
        if ($sessYearId) {
            $found = PlanningYear::find($sessYearId);
            if ($found) {
                return $found;
            }
        }

        $active = PlanningYear::where('is_active', true)->first()
            ?? PlanningYear::orderByDesc('year')->first();

        if (!$active) {
            $active = PlanningYear::create([
                'year' => (int) date('Y'),
                'title' => 'الخطة التشغيلية لبلدية النصيرات للعام ' . date('Y'),
                'is_active' => true,
                'is_entry_open' => true,
            ]);
        }

        session(['selected_year_id' => $active->id]);
        return $active;
    }
}
