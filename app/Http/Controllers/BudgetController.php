<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\MainObjective;
use App\Services\AuditService;
use App\Services\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private EvaluationService $evaluationService)
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear($request->integer('year_id') ?: null);
        $deptId = $user->isDepartment() ? $user->department_id : null;

        $evaluations = $this->evaluationService->evaluateYear($year, $deptId);
        $allocations = BudgetAllocation::where('planning_year_id', $year->id)->get()->keyBy('department_id');
        $departments = Department::orderBy('code')->get();

        // Cost breakdown by Main Objective
        $objectivesCost = MainObjective::where('planning_year_id', $year->id)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($mo) use ($deptId) {
                $acts = Activity::with('followups')
                    ->where('main_objective_id', $mo->id)
                    ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
                    ->get();
                return [
                    'code' => $mo->code,
                    'title' => $mo->title,
                    'count' => $acts->count(),
                    'planned' => (float) $acts->sum('estimated_cost'),
                    'actual' => (float) $acts->sum(fn ($a) => $a->totalActualSpent()),
                ];
            });

        $totals = [
            'allocated' => collect($evaluations)->sum('allocated_budget'),
            'planned' => collect($evaluations)->sum('planned_cost'),
            'actual' => collect($evaluations)->sum('actual_spent'),
        ];
        $totals['remaining'] = $totals['allocated'] - $totals['actual'];

        $chartData = [
            'labels' => array_map(fn ($e) => $e['department']->name, $evaluations),
            'allocated' => array_map(fn ($e) => $e['allocated_budget'], $evaluations),
            'planned' => array_map(fn ($e) => $e['planned_cost'], $evaluations),
            'actual' => array_map(fn ($e) => $e['actual_spent'], $evaluations),
        ];

        return view('budgets.index', compact('year', 'evaluations', 'allocations', 'departments', 'objectivesCost', 'totals', 'chartData'));
    }

    public function store(Request $request): RedirectResponse
    {
        $year = AuditService::getSelectedYear();
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'self_funded' => ['required', 'numeric', 'min:0'],
            'grants_funded' => ['required', 'numeric', 'min:0'],
            'external_funded' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $totalAllocated = (float) $validated['self_funded']
            + (float) $validated['grants_funded']
            + (float) $validated['external_funded'];

        $alloc = BudgetAllocation::updateOrCreate(
            [
                'planning_year_id' => $year->id,
                'department_id' => $validated['department_id'],
            ],
            [
                'allocated_amount' => $totalAllocated,
                'self_funded' => (float) $validated['self_funded'],
                'grants_funded' => (float) $validated['grants_funded'],
                'external_funded' => (float) $validated['external_funded'],
                'notes' => trim((string) ($validated['notes'] ?? '')),
                'updated_by_id' => Auth::id(),
            ]
        );

        $dept = Department::find($validated['department_id']);
        AuditService::log('update', 'budget_allocation', $alloc->id, "موازنة {$dept?->name} ({$year->year})", ['allocated' => $totalAllocated]);

        return back()->with('success', "تم حفظ وتحديث الموازنة المعتمدة لدائرة {$dept?->name} بنجاح.");
    }

    public function destroy(BudgetAllocation $budgetAllocation): RedirectResponse
    {
        $budgetAllocation->update(['deleted_by_id' => Auth::id()]);
        $budgetAllocation->delete();
        AuditService::log('delete', 'budget_allocation', $budgetAllocation->id, 'حذف مخصص موازنة');
        return back()->with('success', 'تم نقل مخصص الموازنة إلى سلة المحذوفات.');
    }
}
