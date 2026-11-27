<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\ActivityVersion;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\MainObjective;
use App\Models\OperationalPlan;
use App\Models\Program;
use App\Models\SubObjective;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear($request->integer('year_id') ?: null);

        $query = Activity::with([
            'department', 'mainObjective', 'subObjective', 'program', 'followups',
        ])
            ->withCount(['attachments', 'comments', 'versions'])
            ->where('planning_year_id', $year->id);

        if ($user->isDepartment()) {
            $query->where('department_id', $user->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('main_objective_id')) {
            $query->where('main_objective_id', $request->integer('main_objective_id'));
        }
        if ($request->filled('sub_objective_id')) {
            $query->where('sub_objective_id', $request->integer('sub_objective_id'));
        }
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->integer('program_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }
        if ($request->filled('support_type')) {
            $query->where('support_type', $request->string('support_type'));
        }
        if ($request->filled('quarter')) {
            $qCol = match ($request->string('quarter')->toString()) {
                'q1' => 'q1', 'q2' => 'q2', 'q3' => 'q3', 'q4' => 'q4', default => null,
            };
            if ($qCol) {
                $query->where($qCol, true);
            }
        }
        if ($request->filled('min_cost')) {
            $query->where('estimated_cost', '>=', (float) $request->input('min_cost'));
        }
        if ($request->filled('max_cost')) {
            $query->where('estimated_cost', '<=', (float) $request->input('max_cost'));
        }
        if ($request->filled('q')) {
            $kw = '%' . trim($request->string('q')->toString()) . '%';
            $query->where(function ($sub) use ($kw) {
                $sub->where('activity_code', 'like', $kw)
                    ->orWhere('title', 'like', $kw)
                    ->orWhere('kpi_name', 'like', $kw)
                    ->orWhere('verification_means', 'like', $kw)
                    ->orWhere('responsible_entity', 'like', $kw)
                    ->orWhere('support_internal', 'like', $kw)
                    ->orWhere('support_external', 'like', $kw);
            });
        }

        $activities = $query->orderBy('main_objective_id')
            ->orderBy('sub_objective_id')
            ->orderBy('program_id')
            ->orderBy('seq_number')
            ->get();

        $mainObjectives = MainObjective::with(['subObjectives.programs'])
            ->where('planning_year_id', $year->id)
            ->orderBy('sort_order')
            ->get();

        $departments = Department::orderBy('code')->get();
        [$entryOpen, $entryMessage] = AuditService::isEntryOpenForDepartment($year->id, $user->department_id);

        return view('activities.index', compact(
            'year',
            'activities',
            'mainObjectives',
            'departments',
            'entryOpen',
            'entryMessage'
        ));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear();

        if ($user->isDepartment()) {
            [$open, $msg] = AuditService::isEntryOpenForDepartment($year->id, $user->department_id);
            if (!$open) {
                return redirect()->route('activities.index')->with('error', $msg);
            }
        }

        $mainObjectives = MainObjective::with(['subObjectives.programs'])
            ->where('planning_year_id', $year->id)
            ->orderBy('sort_order')
            ->get();

        $departments = Department::orderBy('code')->get();

        return view('activities.form', [
            'year' => $year,
            'activity' => null,
            'mainObjectives' => $mainObjectives,
            'departments' => $departments,
            'selectedDepartmentId' => $user->isDepartment() ? $user->department_id : $request->integer('department_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear();

        $departmentId = $user->isDepartment() ? $user->department_id : $request->integer('department_id');
        if (!$departmentId) {
            return back()->withInput()->withErrors(['department_id' => 'يرجى تحديد الدائرة.']);
        }

        if ($user->isDepartment()) {
            [$open, $msg] = AuditService::isEntryOpenForDepartment($year->id, $departmentId);
            if (!$open) {
                return redirect()->route('activities.index')->with('error', $msg);
            }
        }

        $validated = $this->validateActivity($request);
        $this->assertHierarchyConsistency(
            (int) $validated['main_objective_id'],
            (int) $validated['sub_objective_id'],
            (int) $validated['program_id']
        );

        $dept = Department::findOrFail($departmentId);
        $plan = OperationalPlan::firstOrCreate(
            ['planning_year_id' => $year->id, 'department_id' => $dept->id],
            ['title' => "الخطة التشغيلية — {$dept->name} {$year->year}", 'status' => 'draft']
        );

        [$code, $seq] = AuditService::generateActivityCode((int) $validated['program_id'], $year->id);
        $submitNow = $request->input('save_mode') === 'submit';

        $activity = Activity::create([
            'operational_plan_id' => $plan->id,
            'planning_year_id' => $year->id,
            'department_id' => $dept->id,
            'main_objective_id' => $validated['main_objective_id'],
            'sub_objective_id' => $validated['sub_objective_id'],
            'program_id' => $validated['program_id'],
            'activity_code' => $code,
            'seq_number' => $seq,
            'title' => trim($validated['title']),
            'kpi_name' => trim($validated['kpi_name']),
            'kpi_target' => trim($validated['kpi_target']),
            'kpi_target_numeric' => AuditService::parseNumeric($validated['kpi_target']),
            'verification_means' => trim($validated['verification_means']),
            'responsible_entity' => trim($validated['responsible_entity']),
            'support_type' => $validated['support_type'],
            'support_internal' => trim($validated['support_internal'] ?? ''),
            'support_external' => trim($validated['support_external'] ?? ''),
            'q1' => $request->boolean('q1'),
            'q2' => $request->boolean('q2'),
            'q3' => $request->boolean('q3'),
            'q4' => $request->boolean('q4'),
            'priority' => $validated['priority'],
            'estimated_cost' => (float) $validated['estimated_cost'],
            'funding_source' => $validated['funding_source'] ?? 'تمويل ذاتي',
            'budget_item' => $validated['budget_item'] ?? '',
            'status' => $submitNow ? 'pending' : 'draft',
            'current_version' => 1,
            'created_by_id' => $user->id,
        ]);

        AuditService::saveActivityVersion($activity, 'إنشاء النشاط لأول مرة');
        AuditService::syncPlanStatus($plan->id);
        AuditService::log('create', 'activity', $activity->id, "{$activity->activity_code} - {$activity->title}");

        if ($submitNow) {
            AuditService::notifyAdmins(
                'نشاط جديد بانتظار الاعتماد',
                "أرسلت دائرة {$dept->name} النشاط ({$activity->activity_code}) للمراجعة والاعتماد.",
                'submission',
                route('activities.show', $activity),
                $user->id
            );
        }

        return redirect()->route('activities.show', $activity)
            ->with('success', "تم حفظ النشاط بنجاح بالرقم التلقائي ({$activity->activity_code}).");
    }

    public function show(Request $request, Activity $activity): View
    {
        $this->authorizeActivityAccess($activity);

        $activity->load([
            'department', 'planningYear', 'mainObjective', 'subObjective', 'program',
            'reviewer', 'creator', 'versions', 'followups.attachments',
            'attachments.uploader', 'comments.user', 'risks',
        ]);

        // Version comparison
        $versions = $activity->versions;
        $diffRows = [];
        $vOld = null;
        $vNew = null;

        if ($versions->count() >= 2) {
            $oldId = $request->integer('v1') ?: $versions->skip(1)->first()->id;
            $newId = $request->integer('v2') ?: $versions->first()->id;
            $vOld = $versions->firstWhere('id', $oldId) ?? $versions->last();
            $vNew = $versions->firstWhere('id', $newId) ?? $versions->first();
            if ($vOld && $vNew) {
                $diffRows = AuditService::diffSnapshots($vOld->snapshot(), $vNew->snapshot());
            }
        }

        $user = Auth::user();
        [$entryOpen, $entryMessage] = AuditService::isEntryOpenForDepartment($activity->planning_year_id, $activity->department_id);

        return view('activities.show', compact(
            'activity',
            'versions',
            'vOld',
            'vNew',
            'diffRows',
            'entryOpen',
            'entryMessage'
        ));
    }

    public function edit(Activity $activity): View|RedirectResponse
    {
        $this->authorizeActivityAccess($activity);
        $user = Auth::user();

        if ($user->isDepartment()) {
            [$open, $msg] = AuditService::isEntryOpenForDepartment($activity->planning_year_id, $activity->department_id);
            if (!$open) {
                return redirect()->route('activities.show', $activity)->with('error', $msg);
            }
            if ($activity->status === 'approved') {
                return redirect()->route('activities.show', $activity)->with('error', 'لا يمكن تعديل نشاط معتمد إلا بواسطة المشرف العام.');
            }
        }

        $year = $activity->planningYear;
        $mainObjectives = MainObjective::with(['subObjectives.programs'])
            ->where('planning_year_id', $year->id)
            ->orderBy('sort_order')
            ->get();
        $departments = Department::orderBy('code')->get();

        return view('activities.form', [
            'year' => $year,
            'activity' => $activity,
            'mainObjectives' => $mainObjectives,
            'departments' => $departments,
            'selectedDepartmentId' => $activity->department_id,
        ]);
    }

    public function update(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorizeActivityAccess($activity);
        $user = Auth::user();

        if ($user->isDepartment()) {
            [$open, $msg] = AuditService::isEntryOpenForDepartment($activity->planning_year_id, $activity->department_id);
            if (!$open) {
                return redirect()->route('activities.show', $activity)->with('error', $msg);
            }
            if ($activity->status === 'approved') {
                return redirect()->route('activities.show', $activity)->with('error', 'لا يمكن تعديل نشاط معتمد إلا بواسطة المشرف العام.');
            }
        }

        $validated = $this->validateActivity($request);
        $this->assertHierarchyConsistency(
            (int) $validated['main_objective_id'],
            (int) $validated['sub_objective_id'],
            (int) $validated['program_id']
        );

        $programChanged = (int) $activity->program_id !== (int) $validated['program_id'];
        $code = $activity->activity_code;
        $seq = $activity->seq_number;
        if ($programChanged) {
            [$code, $seq] = AuditService::generateActivityCode((int) $validated['program_id'], $activity->planning_year_id);
        }

        $nextStatus = $activity->status;
        if ($request->input('save_mode') === 'submit') {
            $nextStatus = 'pending';
        } elseif ($activity->status === 'rejected' && $user->isDepartment()) {
            $nextStatus = 'draft';
        }

        $activity->update([
            'main_objective_id' => $validated['main_objective_id'],
            'sub_objective_id' => $validated['sub_objective_id'],
            'program_id' => $validated['program_id'],
            'activity_code' => $code,
            'seq_number' => $seq,
            'title' => trim($validated['title']),
            'kpi_name' => trim($validated['kpi_name']),
            'kpi_target' => trim($validated['kpi_target']),
            'kpi_target_numeric' => AuditService::parseNumeric($validated['kpi_target']),
            'verification_means' => trim($validated['verification_means']),
            'responsible_entity' => trim($validated['responsible_entity']),
            'support_type' => $validated['support_type'],
            'support_internal' => trim($validated['support_internal'] ?? ''),
            'support_external' => trim($validated['support_external'] ?? ''),
            'q1' => $request->boolean('q1'),
            'q2' => $request->boolean('q2'),
            'q3' => $request->boolean('q3'),
            'q4' => $request->boolean('q4'),
            'priority' => $validated['priority'],
            'estimated_cost' => (float) $validated['estimated_cost'],
            'funding_source' => $validated['funding_source'] ?? 'تمويل ذاتي',
            'budget_item' => $validated['budget_item'] ?? '',
            'status' => $nextStatus,
            'current_version' => ((int) $activity->current_version) + 1,
        ]);

        $summary = trim($request->input('change_summary', '')) ?: 'تعديل بيانات النشاط';
        AuditService::saveActivityVersion($activity->fresh(), $summary);
        AuditService::syncPlanStatus($activity->operational_plan_id);
        AuditService::log('update', 'activity', $activity->id, "{$activity->activity_code} - {$activity->title}", ['version' => $activity->current_version]);

        if ($request->input('save_mode') === 'submit') {
            AuditService::notifyAdmins(
                'إعادة إرسال نشاط للمراجعة',
                "تم تعديل وإرسال النشاط ({$activity->activity_code}) للمراجعة والاعتماد.",
                'submission',
                route('activities.show', $activity),
                $user->id
            );
        }

        return redirect()->route('activities.show', $activity)
            ->with('success', "تم حفظ التعديلات وتسجيل الإصدار رقم (v{$activity->current_version}) بنجاح.");
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorizeActivityAccess($activity);
        $user = Auth::user();
        if ($user->isDepartment()) {
            [$open, $msg] = AuditService::isEntryOpenForDepartment($activity->planning_year_id, $activity->department_id);
            if (!$open) {
                return back()->with('error', $msg);
            }
            if ($activity->status === 'approved') {
                return back()->with('error', 'لا يمكن حذف نشاط معتمد إلا بواسطة المشرف العام.');
            }
        }

        $planId = $activity->operational_plan_id;
        $activity->update(['deleted_by_id' => $user->id]);
        $activity->delete();
        AuditService::syncPlanStatus($planId);
        AuditService::log('delete', 'activity', $activity->id, "{$activity->activity_code} - {$activity->title}");

        return redirect()->route('activities.index')->with('success', 'تم نقل النشاط إلى سلة المحذوفات بنجاح.');
    }

    public function submit(Activity $activity): RedirectResponse
    {
        $this->authorizeActivityAccess($activity);
        $user = Auth::user();

        if ($user->isDepartment()) {
            [$open, $msg] = AuditService::isEntryOpenForDepartment($activity->planning_year_id, $activity->department_id);
            if (!$open) {
                return back()->with('error', $msg);
            }
        }

        $activity->update(['status' => 'pending']);
        AuditService::syncPlanStatus($activity->operational_plan_id);
        AuditService::log('submit', 'activity', $activity->id, $activity->activity_code);
        AuditService::notifyAdmins(
            'نشاط بانتظار الاعتماد',
            "أرسلت دائرة {$activity->department->name} النشاط ({$activity->activity_code}) للاعتماد.",
            'submission',
            route('activities.show', $activity),
            $user->id
        );

        return back()->with('success', 'تم إرسال النشاط للمشرف العام للاعتماد.');
    }

    public function approve(Request $request, Activity $activity): RedirectResponse
    {
        $note = trim((string) $request->input('review_note', ''));
        $activity->update([
            'status' => 'approved',
            'review_note' => $note ?: 'تم الاعتماد بنجاح.',
            'reviewed_by_id' => Auth::id(),
            'reviewed_at' => now(),
            'current_version' => ((int) $activity->current_version) + 1,
        ]);

        AuditService::saveActivityVersion($activity->fresh(), 'اعتماد النشاط من قبل المشرف العام');
        AuditService::syncPlanStatus($activity->operational_plan_id);
        AuditService::log('approve', 'activity', $activity->id, "{$activity->activity_code} - {$activity->title}", ['note' => $note]);
        AuditService::notifyDepartment(
            $activity->department_id,
            'تم اعتماد نشاط في خطتكم',
            "تم اعتماد النشاط ({$activity->activity_code} — {$activity->title})." . ($note ? " ملاحظة: {$note}" : ''),
            'approval',
            route('activities.show', $activity),
            Auth::id()
        );

        return back()->with('success', "تم اعتماد النشاط ({$activity->activity_code}) بنجاح.");
    }

    public function reject(Request $request, Activity $activity): RedirectResponse
    {
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'review_note.required' => 'يرجى بيان سبب الرفض بشكل واضح للدائرة.',
            'review_note.min' => 'يرجى كتابة سبب واضح لا يقل عن 5 أحرف.',
        ]);

        $activity->update([
            'status' => 'rejected',
            'review_note' => trim($validated['review_note']),
            'reviewed_by_id' => Auth::id(),
            'reviewed_at' => now(),
            'current_version' => ((int) $activity->current_version) + 1,
        ]);

        AuditService::saveActivityVersion($activity->fresh(), 'رفض النشاط مع بيان السبب');
        AuditService::syncPlanStatus($activity->operational_plan_id);
        AuditService::log('reject', 'activity', $activity->id, "{$activity->activity_code} - {$activity->title}", ['reason' => $validated['review_note']]);
        AuditService::notifyDepartment(
            $activity->department_id,
            'تم رفض نشاط — يرجى التعديل',
            "تم رفض النشاط ({$activity->activity_code}) للسبب التالي: {$validated['review_note']}",
            'rejection',
            route('activities.show', $activity),
            Auth::id()
        );

        return back()->with('success', "تم رفض النشاط ({$activity->activity_code}) وإشعار الدائرة بالسبب.");
    }

    public function batchAction(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject,submit,delete'],
            'activity_ids' => ['required', 'array', 'min:1'],
            'activity_ids.*' => ['integer', 'exists:activities,id'],
            'batch_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'activity_ids.required' => 'يرجى تحديد نشاط واحد على الأقل لتنفيذ الإجراء الجماعي.',
        ]);

        $action = $validated['action'];
        if (in_array($action, ['approve', 'reject'], true) && !$user->isAdmin()) {
            abort(403, 'الاعتماد والرفض الجماعي متاح للمشرف العام فقط.');
        }
        if ($action === 'reject' && mb_strlen(trim((string) ($validated['batch_note'] ?? ''))) < 5) {
            return back()->with('error', 'يرجى كتابة سبب الرفض الجماعي (5 أحرف على الأقل).');
        }

        $query = Activity::whereIn('id', $validated['activity_ids']);
        if ($user->isDepartment()) {
            $query->where('department_id', $user->department_id);
        }
        $items = $query->get();

        $planIds = [];
        $count = 0;
        foreach ($items as $act) {
            $planIds[$act->operational_plan_id] = true;
            if ($action === 'approve') {
                $note = trim((string) ($validated['batch_note'] ?? '')) ?: 'اعتماد جماعي من قبل المشرف العام.';
                $act->update([
                    'status' => 'approved',
                    'review_note' => $note,
                    'reviewed_by_id' => $user->id,
                    'reviewed_at' => now(),
                    'current_version' => ((int) $act->current_version) + 1,
                ]);
                AuditService::saveActivityVersion($act->fresh(), 'اعتماد جماعي للنشاط');
                AuditService::notifyDepartment($act->department_id, 'اعتماد نشاط (دفعة واحدة)', "تم اعتماد النشاط ({$act->activity_code}) ضمن الاعتماد الجماعي.", 'approval', route('activities.show', $act), $user->id);
                $count++;
            } elseif ($action === 'reject') {
                $reason = trim((string) $validated['batch_note']);
                $act->update([
                    'status' => 'rejected',
                    'review_note' => $reason,
                    'reviewed_by_id' => $user->id,
                    'reviewed_at' => now(),
                    'current_version' => ((int) $act->current_version) + 1,
                ]);
                AuditService::saveActivityVersion($act->fresh(), 'رفض جماعي مع بيان السبب');
                AuditService::notifyDepartment($act->department_id, 'رفض نشاط (دفعة واحدة)', "تم رفض النشاط ({$act->activity_code}). السبب: {$reason}", 'rejection', route('activities.show', $act), $user->id);
                $count++;
            } elseif ($action === 'submit') {
                if ($user->isDepartment()) {
                    [$open, $msg] = AuditService::isEntryOpenForDepartment($act->planning_year_id, $act->department_id);
                    if (!$open) {
                        return back()->with('error', $msg);
                    }
                }
                if (in_array($act->status, ['draft', 'rejected'], true)) {
                    $act->update(['status' => 'pending']);
                    $count++;
                }
            } elseif ($action === 'delete') {
                if ($user->isDepartment() && $act->status === 'approved') {
                    continue;
                }
                $act->update(['deleted_by_id' => $user->id]);
                $act->delete();
                $count++;
            }
        }

        foreach (array_keys($planIds) as $pid) {
            AuditService::syncPlanStatus((int) $pid);
        }

        if ($action === 'submit' && $count > 0) {
            AuditService::notifyAdmins('إرسال مجموعة أنشطة للمراجعة', "تم إرسال {$count} نشاط للمراجعة والاعتماد.", 'submission', route('activities.index', ['status' => 'pending']), $user->id);
        }

        AuditService::log("batch_{$action}", 'activity', null, "إجراء جماعي على {$count} نشاط", ['ids' => $validated['activity_ids']]);

        $msgMap = [
            'approve' => "تم اعتماد {$count} نشاط دفعة واحدة بنجاح.",
            'reject' => "تم رفض {$count} نشاط دفعة واحدة وإشعار الدوائر.",
            'submit' => "تم إرسال {$count} نشاط للمراجعة والاعتماد.",
            'delete' => "تم نقل {$count} نشاط إلى سلة المحذوفات.",
        ];

        return back()->with('success', $msgMap[$action]);
    }

    public function renumber(): RedirectResponse
    {
        $year = AuditService::getSelectedYear();
        $updated = AuditService::renumberYearActivities($year->id);
        AuditService::log('renumber', 'planning_year', $year->id, "إعادة ترقيم أنشطة عام {$year->year}", ['updated' => $updated]);
        return back()->with('success', "تم إعادة الترقيم التلقائي لأنشطة عام {$year->year} بنجاح ({$updated} نشاط تم تحديث رقمه).");
    }

    public function restoreVersion(Activity $activity, ActivityVersion $version): RedirectResponse
    {
        $this->authorizeActivityAccess($activity);
        if ($version->activity_id !== $activity->id) {
            abort(404);
        }

        $snap = $version->snapshot();
        $activity->update([
            'title' => $snap['title'] ?? $activity->title,
            'kpi_name' => $snap['kpi_name'] ?? $activity->kpi_name,
            'kpi_target' => $snap['kpi_target'] ?? $activity->kpi_target,
            'kpi_target_numeric' => AuditService::parseNumeric($snap['kpi_target'] ?? $activity->kpi_target),
            'verification_means' => $snap['verification_means'] ?? $activity->verification_means,
            'responsible_entity' => $snap['responsible_entity'] ?? $activity->responsible_entity,
            'support_type' => $snap['support_type'] ?? $activity->support_type,
            'support_internal' => $snap['support_internal'] ?? '',
            'support_external' => $snap['support_external'] ?? '',
            'q1' => (bool) ($snap['q1'] ?? false),
            'q2' => (bool) ($snap['q2'] ?? false),
            'q3' => (bool) ($snap['q3'] ?? false),
            'q4' => (bool) ($snap['q4'] ?? false),
            'priority' => $snap['priority'] ?? $activity->priority,
            'estimated_cost' => (float) ($snap['estimated_cost'] ?? $activity->estimated_cost),
            'funding_source' => $snap['funding_source'] ?? $activity->funding_source,
            'budget_item' => $snap['budget_item'] ?? '',
            'current_version' => ((int) $activity->current_version) + 1,
        ]);

        AuditService::saveActivityVersion($activity->fresh(), "استعادة محتوى الإصدار رقم (v{$version->version_number})");
        AuditService::log('restore_version', 'activity', $activity->id, $activity->activity_code, ['restored_from' => $version->version_number]);

        return redirect()->route('activities.show', $activity)
            ->with('success', "تمت استعادة الإصدار (v{$version->version_number}) كإصدار جديد (v{$activity->current_version}).");
    }

    public function uploadAttachment(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorizeActivityAccess($activity);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'monthly_followup_id' => ['nullable', 'exists:monthly_followups,id'],
            'file' => ['required', 'file', 'max:15360', 'mimes:pdf,png,jpg,jpeg,webp,gif,doc,docx,xls,xlsx,csv,txt,ppt,pptx,zip'],
        ], [
            'title.required' => 'يرجى إدخال وصف المرفق أو دليل الإنجاز.',
            'file.required' => 'يرجى اختيار ملف للرفع.',
            'file.max' => 'الحد الأقصى لحجم الملف هو 15 ميجابايت.',
            'file.mimes' => 'نوع الملف غير مسموح به أمنياً.',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $safeStored = 'attachments/' . date('Y/m') . '/' . Str::uuid() . '.' . $ext;
        Storage::disk('local')->putFileAs(dirname($safeStored), $file, basename($safeStored));

        $att = Attachment::create([
            'activity_id' => $activity->id,
            'monthly_followup_id' => $validated['monthly_followup_id'] ?? null,
            'title' => trim($validated['title']),
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $safeStored,
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize() ?: 0,
            'uploaded_by_id' => Auth::id(),
        ]);

        AuditService::log('create', 'attachment', $att->id, "{$att->title} ({$activity->activity_code})");

        return back()->with('success', 'تم رفع المرفق / دليل الإنجاز بنجاح.');
    }

    public function downloadAttachment(Attachment $attachment): StreamedResponse
    {
        $this->authorizeActivityAccess($attachment->activity);
        if (!Storage::disk('local')->exists($attachment->stored_path)) {
            abort(404, 'الملف غير موجود على الخادم.');
        }

        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_filename);
    }

    public function deleteAttachment(Attachment $attachment): RedirectResponse
    {
        $this->authorizeActivityAccess($attachment->activity);
        $attachment->update(['deleted_by_id' => Auth::id()]);
        $attachment->delete();
        AuditService::log('delete', 'attachment', $attachment->id, $attachment->title);
        return back()->with('success', 'تم نقل المرفق إلى سلة المحذوفات.');
    }

    public function storeComment(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorizeActivityAccess($activity);
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'body.required' => 'يرجى كتابة نص التعليق.',
        ]);

        $comment = ActivityComment::create([
            'activity_id' => $activity->id,
            'user_id' => Auth::id(),
            'body' => trim($validated['body']),
        ]);

        $user = Auth::user();
        if ($user->isAdmin()) {
            AuditService::notifyDepartment(
                $activity->department_id,
                'تعليق جديد من المشرف العام',
                "علّق المشرف العام على النشاط ({$activity->activity_code}): " . Str::limit($comment->body, 100),
                'comment',
                route('activities.show', $activity),
                $user->id
            );
        } else {
            AuditService::notifyAdmins(
                'تعليق جديد من الدائرة',
                "أضافت {$activity->department->name} تعليقاً على النشاط ({$activity->activity_code}): " . Str::limit($comment->body, 100),
                'comment',
                route('activities.show', $activity),
                $user->id
            );
        }

        AuditService::log('create', 'activity_comment', $comment->id, "تعليق على {$activity->activity_code}");
        return back()->with('success', 'تم إضافة التعليق بنجاح.');
    }

    public function deleteComment(ActivityComment $comment): RedirectResponse
    {
        $user = Auth::user();
        if (!$user->isAdmin() && $comment->user_id !== $user->id) {
            abort(403);
        }
        $comment->update(['deleted_by_id' => $user->id]);
        $comment->delete();
        return back()->with('success', 'تم حذف التعليق.');
    }

    private function authorizeActivityAccess(Activity $activity): void
    {
        $user = Auth::user();
        if ($user->isDepartment() && (int) $activity->department_id !== (int) $user->department_id) {
            abort(403, 'لا تملك صلاحية الوصول إلى نشاط تابع لدائرة أخرى.');
        }
    }

    private function validateActivity(Request $request): array
    {
        return $request->validate([
            'main_objective_id' => ['required', 'exists:main_objectives,id'],
            'sub_objective_id' => ['required', 'exists:sub_objectives,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'title' => ['required', 'string', 'max:2000'],
            'kpi_name' => ['required', 'string', 'max:1000'],
            'kpi_target' => ['required', 'string', 'max:200'],
            'verification_means' => ['required', 'string', 'max:1500'],
            'responsible_entity' => ['required', 'string', 'max:250'],
            'support_type' => ['required', 'in:internal,external,both,none'],
            'support_internal' => ['nullable', 'string', 'max:250'],
            'support_external' => ['nullable', 'string', 'max:250'],
            'priority' => ['required', 'in:critical,high,medium,low'],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
            'funding_source' => ['nullable', 'string', 'max:80'],
            'budget_item' => ['nullable', 'string', 'max:150'],
        ], [
            'main_objective_id.required' => 'يرجى اختيار الهدف الرئيسي.',
            'sub_objective_id.required' => 'يرجى اختيار الهدف الفرعي.',
            'program_id.required' => 'يرجى اختيار البرنامج.',
            'title.required' => 'يرجى إدخال وصف النشاط.',
            'kpi_name.required' => 'يرجى إدخال مؤشر الأداء.',
            'kpi_target.required' => 'يرجى إدخال قيمة المؤشر.',
            'verification_means.required' => 'يرجى إدخال وسائل التحقق.',
            'responsible_entity.required' => 'يرجى إدخال الجهة المسئولة.',
            'estimated_cost.required' => 'يرجى إدخال التكلفة التقديرية (أو 0).',
        ]);
    }

    private function assertHierarchyConsistency(int $mainId, int $subId, int $progId): void
    {
        $sub = SubObjective::where('id', $subId)->where('main_objective_id', $mainId)->first();
        if (!$sub) {
            abort(422, 'الهدف الفرعي المحدد لا يتبع للهدف الرئيسي المختار.');
        }
        $prog = Program::where('id', $progId)->where('sub_objective_id', $subId)->first();
        if (!$prog) {
            abort(422, 'البرنامج المحدد لا يتبع للهدف الفرعي المختار.');
        }
    }
}
