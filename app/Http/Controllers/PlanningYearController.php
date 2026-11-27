<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\MainObjective;
use App\Models\OperationalPlan;
use App\Models\PlanningYear;
use App\Models\Program;
use App\Models\SubObjective;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlanningYearController extends Controller
{
    public function index(): View
    {
        $years = PlanningYear::withCount(['mainObjectives', 'operationalPlans', 'activities'])
            ->orderByDesc('year')
            ->get();
        $departments = Department::orderBy('code')->get();
        return view('years.index', compact('years', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100', 'unique:planning_years,year'],
            'title' => ['nullable', 'string', 'max:200'],
            'is_entry_open' => ['nullable', 'boolean'],
            'entry_start_date' => ['nullable', 'date'],
            'entry_end_date' => ['nullable', 'date', 'after_or_equal:entry_start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'set_active' => ['nullable', 'boolean'],
        ], [
            'year.unique' => 'هذه السنة التخطيطية مسجلة مسبقاً.',
        ]);

        DB::transaction(function () use ($validated, $request) {
            if ($request->boolean('set_active')) {
                PlanningYear::query()->update(['is_active' => false]);
            }

            $py = PlanningYear::create([
                'year' => (int) $validated['year'],
                'title' => trim((string) ($validated['title'] ?? '')) ?: "الخطة التشغيلية لبلدية النصيرات للعام {$validated['year']}",
                'is_active' => $request->boolean('set_active'),
                'is_entry_open' => $request->boolean('is_entry_open', true),
                'entry_start_date' => ($validated['entry_start_date'] ?? null) ?: null,
                'entry_end_date' => ($validated['entry_end_date'] ?? null) ?: null,
                'notes' => trim((string) ($validated['notes'] ?? '')),
            ]);

            if ($py->is_active) {
                session(['selected_year_id' => $py->id]);
            }

            AuditService::log('create', 'planning_year', $py->id, $py->title);
        });

        return back()->with('success', 'تم إنشاء السنة التخطيطية الجديدة بنجاح.');
    }

    public function update(Request $request, PlanningYear $planningYear): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'is_entry_open' => ['nullable', 'boolean'],
            'entry_start_date' => ['nullable', 'date'],
            'entry_end_date' => ['nullable', 'date', 'after_or_equal:entry_start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $wasOpen = (bool) $planningYear->is_entry_open;
        $nowOpen = $request->boolean('is_entry_open');

        $planningYear->update([
            'title' => trim($validated['title']),
            'is_entry_open' => $nowOpen,
            'entry_start_date' => ($validated['entry_start_date'] ?? null) ?: null,
            'entry_end_date' => ($validated['entry_end_date'] ?? null) ?: null,
            'notes' => trim((string) ($validated['notes'] ?? '')),
        ]);

        if ($wasOpen !== $nowOpen) {
            $stateText = $nowOpen ? 'فتح' : 'إغلاق';
            foreach (Department::all() as $dept) {
                AuditService::notifyDepartment(
                    $dept->id,
                    "تم {$stateText} فترة إدخال الخطط لعام {$planningYear->year}",
                    "قام المشرف العام بـ {$stateText} فترة إدخال وتعديل الخطط التشغيلية للعام {$planningYear->year}.",
                    'period',
                    route('activities.index'),
                    Auth::id()
                );
            }
            AuditService::log('period_toggle', 'planning_year', $planningYear->id, "{$stateText} فترة الإدخال {$planningYear->year}");
        } else {
            AuditService::log('update', 'planning_year', $planningYear->id, $planningYear->title);
        }

        return back()->with('success', "تم تحديث إعدادات السنة التخطيطية ({$planningYear->year}) بنجاح.");
    }

    public function toggleEntry(PlanningYear $planningYear): RedirectResponse
    {
        $next = !$planningYear->is_entry_open;
        $planningYear->update(['is_entry_open' => $next]);

        $stateText = $next ? 'فتح' : 'إغلاق';
        foreach (Department::all() as $dept) {
            AuditService::notifyDepartment(
                $dept->id,
                "تم {$stateText} فترة إدخال خطط {$planningYear->year}",
                "أصبحت فترة إدخال الأنشطة للعام {$planningYear->year} " . ($next ? 'مفتوحة الآن' : 'مغلقة حالياً') . '.',
                'period',
                route('activities.index'),
                Auth::id()
            );
        }

        AuditService::log('period_toggle', 'planning_year', $planningYear->id, "{$stateText} فترة الإدخال {$planningYear->year}");
        return back()->with('success', "تم {$stateText} فترة إدخال الخطط لعام {$planningYear->year} وإشعار كافة الدوائر.");
    }

    public function setActive(PlanningYear $planningYear): RedirectResponse
    {
        PlanningYear::query()->update(['is_active' => false]);
        $planningYear->update(['is_active' => true]);
        session(['selected_year_id' => $planningYear->id]);
        AuditService::log('update', 'planning_year', $planningYear->id, "تفعيل السنة {$planningYear->year}");

        return back()->with('success', "أصبحت السنة {$planningYear->year} هي السنة التخطيطية النشطة الافتراضية.");
    }

    public function clonePlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_year_id' => ['required', 'exists:planning_years,id'],
            'target_year_id' => ['required', 'exists:planning_years,id', 'different:source_year_id'],
            'clone_activities' => ['nullable', 'boolean'],
            'clone_budgets' => ['nullable', 'boolean'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ], [
            'target_year_id.different' => 'يجب اختيار سنة هدف مختلفة عن السنة المصدر.',
        ]);

        $source = PlanningYear::findOrFail($validated['source_year_id']);
        $target = PlanningYear::findOrFail($validated['target_year_id']);
        $cloneActivities = $request->boolean('clone_activities', true);
        $cloneBudgets = $request->boolean('clone_budgets', true);
        $deptFilter = ($validated['department_id'] ?? null) ?: null;

        $counts = ['objectives' => 0, 'programs' => 0, 'activities' => 0, 'budgets' => 0];

        DB::transaction(function () use ($source, $target, $cloneActivities, $cloneBudgets, $deptFilter, &$counts) {
            $mainMap = [];
            $subMap = [];
            $progMap = [];

            // 1. Clone Strategic Hierarchy
            $sourceMains = MainObjective::with('subObjectives.programs')
                ->where('planning_year_id', $source->id)
                ->orderBy('sort_order')
                ->get();

            foreach ($sourceMains as $mo) {
                $newMain = MainObjective::firstOrCreate(
                    ['planning_year_id' => $target->id, 'code' => $mo->code],
                    ['title' => $mo->title, 'description' => $mo->description, 'sort_order' => $mo->sort_order]
                );
                $mainMap[$mo->id] = $newMain->id;
                $counts['objectives']++;

                foreach ($mo->subObjectives as $so) {
                    $newSub = SubObjective::firstOrCreate(
                        ['main_objective_id' => $newMain->id, 'code' => $so->code],
                        ['title' => $so->title, 'description' => $so->description, 'sort_order' => $so->sort_order]
                    );
                    $subMap[$so->id] = $newSub->id;

                    foreach ($so->programs as $pr) {
                        $newProg = Program::firstOrCreate(
                            ['sub_objective_id' => $newSub->id, 'code' => $pr->code],
                            ['title' => $pr->title, 'description' => $pr->description, 'sort_order' => $pr->sort_order]
                        );
                        $progMap[$pr->id] = $newProg->id;
                        $counts['programs']++;
                    }
                }
            }

            // 2. Clone Operational Plans & Activities (reset to draft with fresh automatic numbering)
            if ($cloneActivities) {
                $sourceActs = Activity::with('department')
                    ->where('planning_year_id', $source->id)
                    ->when($deptFilter, fn ($q) => $q->where('department_id', $deptFilter))
                    ->orderBy('id')
                    ->get();

                $planCache = [];
                foreach ($sourceActs as $act) {
                    if (!isset($mainMap[$act->main_objective_id], $subMap[$act->sub_objective_id], $progMap[$act->program_id])) {
                        continue;
                    }
                    if (!isset($planCache[$act->department_id])) {
                        $planCache[$act->department_id] = OperationalPlan::firstOrCreate(
                            ['planning_year_id' => $target->id, 'department_id' => $act->department_id],
                            ['title' => "الخطة التشغيلية — {$act->department?->name} {$target->year}", 'status' => 'draft']
                        );
                    }
                    $newProgId = $progMap[$act->program_id];
                    [$newCode, $newSeq] = AuditService::generateActivityCode($newProgId, $target->id);

                    $cloned = Activity::create([
                        'operational_plan_id' => $planCache[$act->department_id]->id,
                        'planning_year_id' => $target->id,
                        'department_id' => $act->department_id,
                        'main_objective_id' => $mainMap[$act->main_objective_id],
                        'sub_objective_id' => $subMap[$act->sub_objective_id],
                        'program_id' => $newProgId,
                        'activity_code' => $newCode,
                        'seq_number' => $newSeq,
                        'title' => $act->title,
                        'kpi_name' => $act->kpi_name,
                        'kpi_target' => $act->kpi_target,
                        'kpi_target_numeric' => $act->kpi_target_numeric,
                        'verification_means' => $act->verification_means,
                        'responsible_entity' => $act->responsible_entity,
                        'support_type' => $act->support_type,
                        'support_internal' => $act->support_internal,
                        'support_external' => $act->support_external,
                        'q1' => $act->q1, 'q2' => $act->q2, 'q3' => $act->q3, 'q4' => $act->q4,
                        'priority' => $act->priority,
                        'estimated_cost' => $act->estimated_cost,
                        'funding_source' => $act->funding_source,
                        'budget_item' => $act->budget_item,
                        'status' => 'draft',
                        'review_note' => '',
                        'current_version' => 1,
                        'created_by_id' => Auth::id(),
                    ]);
                    AuditService::saveActivityVersion($cloned, "نسخ تلقائي من خطة عام {$source->year}");
                    $counts['activities']++;
                }
            }

            // 3. Clone Budget Allocations
            if ($cloneBudgets) {
                $srcBudgets = BudgetAllocation::where('planning_year_id', $source->id)
                    ->when($deptFilter, fn ($q) => $q->where('department_id', $deptFilter))
                    ->get();
                foreach ($srcBudgets as $b) {
                    BudgetAllocation::updateOrCreate(
                        ['planning_year_id' => $target->id, 'department_id' => $b->department_id],
                        [
                            'allocated_amount' => $b->allocated_amount,
                            'self_funded' => $b->self_funded,
                            'grants_funded' => $b->grants_funded,
                            'external_funded' => $b->external_funded,
                            'notes' => "منسوخة من موازنة عام {$source->year}",
                            'updated_by_id' => Auth::id(),
                        ]
                    );
                    $counts['budgets']++;
                }
            }
        });

        AuditService::log('clone_year', 'planning_year', $target->id, "نسخ من {$source->year} إلى {$target->year}", $counts);
        session(['selected_year_id' => $target->id]);

        return back()->with('success', "تم نسخ الخطة من عام {$source->year} إلى عام {$target->year} بنجاح ({$counts['objectives']} أهداف رئيسية، {$counts['programs']} برامج، {$counts['activities']} أنشطة، {$counts['budgets']} موازنات).");
    }

    public function destroy(PlanningYear $planningYear): RedirectResponse
    {
        if ($planningYear->is_active) {
            return back()->with('error', 'لا يمكن حذف السنة التخطيطية النشطة حالياً. قم بتفعيل سنة أخرى أولاً.');
        }
        $planningYear->update(['deleted_by_id' => Auth::id()]);
        $planningYear->delete();
        AuditService::log('delete', 'planning_year', $planningYear->id, $planningYear->title);
        return back()->with('success', 'تم نقل السنة التخطيطية إلى سلة المحذوفات.');
    }
}
