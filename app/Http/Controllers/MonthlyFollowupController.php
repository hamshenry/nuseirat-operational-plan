<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\MonthlyFollowup;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MonthlyFollowupController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear($request->integer('year_id') ?: null);
        $month = max(1, min(12, $request->integer('month') ?: (int) date('n')));

        $query = Activity::with([
            'department', 'program', 'followups', 'attachments',
        ])->where('planning_year_id', $year->id);

        if ($user->isDepartment()) {
            $query->where('department_id', $user->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $activities = $query->orderBy('department_id')->orderBy('activity_code')->get();
        $departments = Department::orderBy('code')->get();

        // Monthly summary stats
        $monthEntries = MonthlyFollowup::where('planning_year_id', $year->id)
            ->where('month', $month)
            ->when($user->isDepartment(), fn ($q) => $q->whereHas('activity', fn ($aq) => $aq->where('department_id', $user->department_id)))
            ->get();

        $summary = [
            'tracked_count' => $monthEntries->count(),
            'avg_planned' => $monthEntries->count() ? round($monthEntries->avg('planned_percent'), 1) : 0,
            'avg_actual' => $monthEntries->count() ? round($monthEntries->avg('actual_percent'), 1) : 0,
            'month_spent' => (float) $monthEntries->sum('actual_cost'),
            'completed' => $monthEntries->where('execution_status', 'completed')->count(),
            'delayed_or_blocked' => $monthEntries->whereIn('execution_status', ['delayed', 'blocked'])->count(),
        ];

        return view('followups.index', compact('year', 'month', 'activities', 'departments', 'summary'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'activity_id' => ['required', 'exists:activities,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'planned_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'actual_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'achieved_value' => ['nullable', 'string', 'max:200'],
            'actual_cost' => ['required', 'numeric', 'min:0'],
            'execution_status' => ['required', 'in:not_started,in_progress,completed,delayed,blocked'],
            'achievements_note' => ['nullable', 'string', 'max:2000'],
            'obstacles_note' => ['nullable', 'string', 'max:2000'],
            'next_steps' => ['nullable', 'string', 'max:2000'],
            'evidence_file' => ['nullable', 'file', 'max:15360', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,zip'],
            'evidence_title' => ['nullable', 'string', 'max:250'],
        ]);

        $activity = Activity::findOrFail($validated['activity_id']);
        if ($user->isDepartment() && (int) $activity->department_id !== (int) $user->department_id) {
            abort(403, 'لا يمكنك تعديل متابعة نشاط لدائرة أخرى.');
        }

        $followup = MonthlyFollowup::updateOrCreate(
            [
                'activity_id' => $activity->id,
                'month' => (int) $validated['month'],
            ],
            [
                'planning_year_id' => $activity->planning_year_id,
                'planned_percent' => (float) $validated['planned_percent'],
                'actual_percent' => (float) $validated['actual_percent'],
                'achieved_value' => trim((string) ($validated['achieved_value'] ?? '')),
                'actual_cost' => (float) $validated['actual_cost'],
                'execution_status' => $validated['execution_status'],
                'achievements_note' => trim((string) ($validated['achievements_note'] ?? '')),
                'obstacles_note' => trim((string) ($validated['obstacles_note'] ?? '')),
                'next_steps' => trim((string) ($validated['next_steps'] ?? '')),
                'updated_by_id' => $user->id,
            ]
        );

        if ($request->hasFile('evidence_file')) {
            $file = $request->file('evidence_file');
            $ext = strtolower($file->getClientOriginalExtension());
            $safeStored = 'attachments/' . date('Y/m') . '/' . Str::uuid() . '.' . $ext;
            Storage::disk('local')->putFileAs(dirname($safeStored), $file, basename($safeStored));

            Attachment::create([
                'activity_id' => $activity->id,
                'monthly_followup_id' => $followup->id,
                'title' => trim((string) ($validated['evidence_title'] ?? '')) ?: ('دليل إنجاز شهر ' . MonthlyFollowup::monthName((int) $validated['month'])),
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $safeStored,
                'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize() ?: 0,
                'uploaded_by_id' => $user->id,
            ]);
        }

        AuditService::log('update', 'monthly_followup', $followup->id, "{$activity->activity_code} — شهر {$validated['month']}", [
            'actual_percent' => $followup->actual_percent,
            'actual_cost' => $followup->actual_cost,
        ]);

        if (in_array($validated['execution_status'], ['delayed', 'blocked'], true) && $user->isDepartment()) {
            AuditService::notifyAdmins(
                'تنبيه تأخر/تعثر في المتابعة الشهرية',
                "النشاط ({$activity->activity_code}) سُجل بحالة ({$followup->executionStatusLabel()}) لشهر " . MonthlyFollowup::monthName((int) $validated['month']) . '.',
                'risk',
                route('followups.index', ['month' => $validated['month']]),
                $user->id
            );
        }

        return back()->with('success', 'تم حفظ بيانات المتابعة الشهرية للنشاط بنجاح.');
    }

    public function destroy(MonthlyFollowup $followup): RedirectResponse
    {
        $user = Auth::user();
        if ($user->isDepartment() && (int) $followup->activity->department_id !== (int) $user->department_id) {
            abort(403);
        }
        $followup->update(['deleted_by_id' => $user->id]);
        $followup->delete();
        AuditService::log('delete', 'monthly_followup', $followup->id, "متابعة شهر {$followup->month}");
        return back()->with('success', 'تم نقل سجل المتابعة الشهرية إلى سلة المحذوفات.');
    }
}
