<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\PlanningYear;

class EvaluationService
{
    /**
     * Evaluate all departments for a planning year across 5 weighted pillars (total 100):
     * 1. Execution Completion Rate (30%)
     * 2. Activity Approval & Quality Rate (25%)
     * 3. Quarterly Timeliness / On-Track Rate (20%)
     * 4. Budget Adherence & Cost Efficiency (15%)
     * 5. Monthly Follow-up & Evidence Documentation (10%)
     */
    public function evaluateYear(PlanningYear $year, ?int $departmentId = null): array
    {
        $departmentsQuery = Department::orderBy('code');
        if ($departmentId) {
            $departmentsQuery->where('id', $departmentId);
        }
        $departments = $departmentsQuery->get();

        $activitiesByDept = Activity::with(['followups', 'attachments'])
            ->where('planning_year_id', $year->id)
            ->get()
            ->groupBy('department_id');

        $budgetsByDept = BudgetAllocation::where('planning_year_id', $year->id)
            ->get()
            ->keyBy('department_id');

        $rows = [];
        foreach ($departments as $dept) {
            $acts = $activitiesByDept->get($dept->id, collect());
            $total = $acts->count();
            $approved = $acts->where('status', 'approved')->count();
            $pending = $acts->where('status', 'pending')->count();
            $rejected = $acts->where('status', 'rejected')->count();
            $draft = $acts->where('status', 'draft')->count();

            $plannedCost = (float) $acts->sum('estimated_cost');
            $actualSpent = (float) $acts->sum(fn ($a) => $a->totalActualSpent());
            $allocatedBudget = (float) ($budgetsByDept->get($dept->id)?->allocated_amount ?? 0);

            // 1. Execution progress average across activities
            $avgProgress = $total > 0
                ? round($acts->avg(fn ($a) => $a->latestProgress()), 1)
                : 0.0;

            // 2. Approval rate
            $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0.0;

            // 3. Timeliness (activities whose latest followup is NOT delayed or blocked)
            $trackedActs = $acts->filter(fn ($a) => $a->followups->count() > 0);
            $onTrack = $trackedActs->filter(function ($a) {
                $latest = $a->followups->sortByDesc('month')->first();
                return $latest && !in_array($latest->execution_status, ['delayed', 'blocked'], true);
            })->count();
            $timelinessRate = $trackedActs->count() > 0
                ? round(($onTrack / $trackedActs->count()) * 100, 1)
                : ($total > 0 ? 50.0 : 0.0);

            // 4. Budget adherence
            $budgetScore = 100.0;
            if ($allocatedBudget > 0) {
                if ($plannedCost > $allocatedBudget) {
                    $overrunRatio = ($plannedCost - $allocatedBudget) / $allocatedBudget;
                    $budgetScore = max(20.0, round(100 - ($overrunRatio * 100), 1));
                } elseif ($actualSpent > $allocatedBudget) {
                    $budgetScore = 40.0;
                } else {
                    $budgetScore = 100.0;
                }
            } elseif ($total === 0) {
                $budgetScore = 0.0;
            }

            // 5. Documentation & Follow-up completeness
            $withFollowup = $acts->filter(fn ($a) => $a->followups->count() > 0)->count();
            $withAttachment = $acts->filter(fn ($a) => $a->attachments->count() > 0)->count();
            $docRate = $total > 0
                ? round((($withFollowup / $total) * 70) + (($withAttachment / $total) * 30), 1)
                : 0.0;

            $composite = $total > 0
                ? round(
                    ($avgProgress * 0.30)
                    + ($approvalRate * 0.25)
                    + ($timelinessRate * 0.20)
                    + ($budgetScore * 0.15)
                    + ($docRate * 0.10),
                    1
                )
                : 0.0;

            [$grade, $gradeClass] = match (true) {
                $composite >= 85 => ['ممتاز', 'badge-success'],
                $composite >= 70 => ['جيد جداً', 'badge-info'],
                $composite >= 55 => ['جيد', 'badge-primary'],
                $composite >= 40 => ['مقبول', 'badge-warning'],
                default => ['يحتاج تحسين', 'badge-danger'],
            };

            $rows[] = [
                'department' => $dept,
                'total_activities' => $total,
                'approved' => $approved,
                'pending' => $pending,
                'rejected' => $rejected,
                'draft' => $draft,
                'avg_progress' => $avgProgress,
                'approval_rate' => $approvalRate,
                'timeliness_rate' => $timelinessRate,
                'budget_score' => $budgetScore,
                'doc_rate' => $docRate,
                'allocated_budget' => $allocatedBudget,
                'planned_cost' => $plannedCost,
                'actual_spent' => $actualSpent,
                'attachments_count' => $acts->sum(fn ($a) => $a->attachments->count()),
                'followups_count' => $acts->sum(fn ($a) => $a->followups->count()),
                'score' => $composite,
                'grade' => $grade,
                'grade_class' => $gradeClass,
            ];
        }

        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

        $rank = 1;
        foreach ($rows as &$r) {
            $r['rank'] = $rank++;
        }

        return $rows;
    }
}
