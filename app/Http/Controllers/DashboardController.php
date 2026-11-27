<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\MonthlyFollowup;
use App\Models\OperationalPlan;
use App\Models\PlanningYear;
use App\Models\RiskObstacle;
use App\Services\AuditService;
use App\Services\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private EvaluationService $evaluationService)
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear($request->integer('year_id') ?: null);
        $deptFilterId = $user->isDepartment() ? $user->department_id : null;

        $plansQuery = OperationalPlan::with(['department', 'planningYear'])
            ->withCount([
                'activities',
                'activities as approved_count' => fn ($q) => $q->where('status', 'approved'),
                'activities as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'activities as rejected_count' => fn ($q) => $q->where('status', 'rejected'),
            ])
            ->where('planning_year_id', $year->id);

        $activitiesQuery = Activity::with(['department', 'followups'])
            ->where('planning_year_id', $year->id);

        $risksQuery = RiskObstacle::with(['department'])
            ->where('planning_year_id', $year->id);

        $budgetsQuery = BudgetAllocation::where('planning_year_id', $year->id);

        if ($deptFilterId) {
            $plansQuery->where('department_id', $deptFilterId);
            $activitiesQuery->where('department_id', $deptFilterId);
            $risksQuery->where('department_id', $deptFilterId);
            $budgetsQuery->where('department_id', $deptFilterId);
        }

        $plans = $plansQuery->orderBy('department_id')->get();
        $activities = $activitiesQuery->get();
        $risks = $risksQuery->orderByDesc('risk_score')->get();

        $totalActivities = $activities->count();
        $approvedActivities = $activities->where('status', 'approved')->count();
        $pendingActivities = $activities->where('status', 'pending')->count();
        $rejectedActivities = $activities->where('status', 'rejected')->count();
        $draftActivities = $activities->where('status', 'draft')->count();

        $totalAllocated = (float) $budgetsQuery->sum('allocated_amount');
        $totalPlannedCost = (float) $activities->sum('estimated_cost');
        $totalActualSpent = (float) $activities->sum(fn ($a) => $a->totalActualSpent());
        $avgProgress = $totalActivities > 0
            ? round($activities->avg(fn ($a) => $a->latestProgress()), 1)
            : 0.0;

        $evaluations = $this->evaluationService->evaluateYear($year, $deptFilterId);

        // Quarterly counts for Chart.js
        $quarterlyCounts = [
            $activities->where('q1', true)->count(),
            $activities->where('q2', true)->count(),
            $activities->where('q3', true)->count(),
            $activities->where('q4', true)->count(),
        ];

        // Monthly progress trend (months 1..12)
        $monthlyPlanned = [];
        $monthlyActual = [];
        $followupsByMonth = MonthlyFollowup::where('planning_year_id', $year->id)
            ->when($deptFilterId, fn ($q) => $q->whereHas('activity', fn ($aq) => $aq->where('department_id', $deptFilterId)))
            ->get()
            ->groupBy('month');

        for ($m = 1; $m <= 12; $m++) {
            $grp = $followupsByMonth->get($m, collect());
            $monthlyPlanned[] = $grp->count() > 0 ? round($grp->avg('planned_percent'), 1) : 0;
            $monthlyActual[] = $grp->count() > 0 ? round($grp->avg('actual_percent'), 1) : 0;
        }

        $chartData = [
            'status' => [$approvedActivities, $pendingActivities, $rejectedActivities, $draftActivities],
            'quarters' => $quarterlyCounts,
            'deptLabels' => array_map(fn ($e) => $e['department']->name, $evaluations),
            'deptCounts' => array_map(fn ($e) => $e['total_activities'], $evaluations),
            'deptScores' => array_map(fn ($e) => $e['score'], $evaluations),
            'deptAllocated' => array_map(fn ($e) => $e['allocated_budget'], $evaluations),
            'deptPlanned' => array_map(fn ($e) => $e['planned_cost'], $evaluations),
            'deptSpent' => array_map(fn ($e) => $e['actual_spent'], $evaluations),
            'monthlyPlanned' => $monthlyPlanned,
            'monthlyActual' => $monthlyActual,
        ];

        [$entryOpen, $entryMessage] = AuditService::isEntryOpenForDepartment($year->id, $deptFilterId);

        return view('dashboard.index', [
            'year' => $year,
            'plans' => $plans,
            'departments' => Department::orderBy('code')->get(),
            'kpis' => [
                'plans_total' => $plans->count(),
                'plans_approved' => $plans->where('status', 'approved')->count(),
                'plans_pending' => $plans->where('status', 'submitted')->count(),
                'activities_total' => $totalActivities,
                'activities_approved' => $approvedActivities,
                'activities_pending' => $pendingActivities,
                'activities_rejected' => $rejectedActivities,
                'activities_draft' => $draftActivities,
                'total_allocated' => $totalAllocated,
                'total_planned_cost' => $totalPlannedCost,
                'total_actual_spent' => $totalActualSpent,
                'avg_progress' => $avgProgress,
                'critical_risks' => $risks->whereIn('severity', ['critical', 'high'])->where('status', '!=', 'resolved')->count(),
                'open_risks' => $risks->where('status', '!=', 'resolved')->count(),
            ],
            'evaluations' => $evaluations,
            'recentRisks' => $risks->take(5),
            'chartData' => $chartData,
            'entryOpen' => $entryOpen,
            'entryMessage' => $entryMessage,
        ]);
    }

    public function switchYear(Request $request): RedirectResponse
    {
        $request->validate(['year_id' => ['required', 'integer', 'exists:planning_years,id']]);
        session(['selected_year_id' => $request->integer('year_id')]);
        return back()->with('success', 'تم تبديل السنة التخطيطية المعروضة.');
    }

    public function createPlan(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'planning_year_id' => ['required', 'integer', 'exists:planning_years,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($user->isDepartment() && (int) $validated['department_id'] !== (int) $user->department_id) {
            abort(403, 'لا يمكنك إنشاء خطة لدائرة أخرى.');
        }

        if ($user->isDepartment()) {
            [$open, $msg] = AuditService::isEntryOpenForDepartment((int) $validated['planning_year_id'], $user->department_id);
            if (!$open) {
                return back()->with('error', $msg);
            }
        }

        $year = PlanningYear::findOrFail($validated['planning_year_id']);
        $dept = Department::findOrFail($validated['department_id']);

        $plan = OperationalPlan::firstOrCreate(
            [
                'planning_year_id' => $year->id,
                'department_id' => $dept->id,
            ],
            [
                'title' => "الخطة التشغيلية — {$dept->name} {$year->year}",
                'status' => 'draft',
                'notes' => $validated['notes'] ?? '',
            ]
        );

        AuditService::log('create', 'operational_plan', $plan->id, $plan->title);

        return redirect()->route('activities.index', ['department_id' => $dept->id])
            ->with('success', "تم فتح/إنشاء الخطة التشغيلية لدائرة {$dept->name} لعام {$year->year}.");
    }
}
