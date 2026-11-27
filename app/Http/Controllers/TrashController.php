<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\Attachment;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\InternalMessage;
use App\Models\MainObjective;
use App\Models\MonthlyFollowup;
use App\Models\OperationalPlan;
use App\Models\PlanningYear;
use App\Models\Program;
use App\Models\RiskObstacle;
use App\Models\SubObjective;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TrashController extends Controller
{
    private const MODEL_MAP = [
        'activity' => [Activity::class, 'الأنشطة', 'title'],
        'operational_plan' => [OperationalPlan::class, 'الخطط التشغيلية', 'title'],
        'department' => [Department::class, 'الدوائر', 'name'],
        'user' => [User::class, 'المستخدمون', 'name'],
        'main_objective' => [MainObjective::class, 'الأهداف الرئيسية', 'title'],
        'sub_objective' => [SubObjective::class, 'الأهداف الفرعية', 'title'],
        'program' => [Program::class, 'البرامج', 'title'],
        'monthly_followup' => [MonthlyFollowup::class, 'المتابعة الشهرية', 'id'],
        'budget_allocation' => [BudgetAllocation::class, 'الموازنات', 'id'],
        'risk_obstacle' => [RiskObstacle::class, 'المخاطر والمعوقات', 'title'],
        'attachment' => [Attachment::class, 'المرفقات والأدلة', 'title'],
        'activity_comment' => [ActivityComment::class, 'التعليقات', 'body'],
        'internal_message' => [InternalMessage::class, 'المراسلات الداخلية', 'subject'],
        'planning_year' => [PlanningYear::class, 'السنوات التخطيطية', 'title'],
    ];

    public function index(Request $request): View
    {
        $selectedType = $request->input('type', 'all');
        $items = [];
        $counts = [];

        foreach (self::MODEL_MAP as $typeKey => [$modelClass, $typeLabel, $labelCol]) {
            $trashed = $modelClass::onlyTrashed()->orderByDesc('deleted_at')->get();
            $counts[$typeKey] = ['label' => $typeLabel, 'count' => $trashed->count()];

            if ($selectedType === 'all' || $selectedType === $typeKey) {
                foreach ($trashed as $record) {
                    $display = (string) ($record->{$labelCol} ?? ('#' . $record->id));
                    if ($typeKey === 'activity' && !empty($record->activity_code)) {
                        $display = "{$record->activity_code} — {$display}";
                    }
                    $items[] = [
                        'type' => $typeKey,
                        'type_label' => $typeLabel,
                        'id' => $record->id,
                        'label' => mb_strimwidth($display, 0, 120, '...'),
                        'deleted_at' => $record->deleted_at,
                    ];
                }
            }
        }

        usort($items, fn ($a, $b) => ($b['deleted_at']?->timestamp ?? 0) <=> ($a['deleted_at']?->timestamp ?? 0));

        return view('trash.index', compact('items', 'counts', 'selectedType'));
    }

    public function restore(string $type, int $id): RedirectResponse
    {
        if (!isset(self::MODEL_MAP[$type])) {
            abort(404);
        }
        [$modelClass, , $labelCol] = self::MODEL_MAP[$type];
        $record = $modelClass::onlyTrashed()->findOrFail($id);
        $record->restore();

        if ($type === 'user') {
            $record->update(['is_active' => true]);
        }
        if ($type === 'activity') {
            AuditService::syncPlanStatus($record->operational_plan_id);
        }

        AuditService::log('restore', $type, $id, (string) ($record->{$labelCol} ?? "#{$id}"));
        return back()->with('success', 'تمت استعادة العنصر من سلة المحذوفات بنجاح.');
    }

    public function forceDelete(string $type, int $id): RedirectResponse
    {
        if (!isset(self::MODEL_MAP[$type])) {
            abort(404);
        }
        [$modelClass, , $labelCol] = self::MODEL_MAP[$type];
        $record = $modelClass::onlyTrashed()->findOrFail($id);
        $label = (string) ($record->{$labelCol} ?? "#{$id}");

        if ($type === 'attachment' && !empty($record->stored_path)) {
            Storage::disk('local')->delete($record->stored_path);
        }

        $record->forceDelete();
        AuditService::log('force_delete', $type, $id, $label);
        return back()->with('success', 'تم حذف العنصر نهائياً من قاعدة البيانات.');
    }
}
