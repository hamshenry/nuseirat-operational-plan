<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\EvaluationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EvaluationController extends Controller
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

        $municipalityAvg = count($evaluations) > 0
            ? round(collect($evaluations)->avg('score'), 1)
            : 0.0;

        $chartData = [
            'labels' => array_map(fn ($e) => $e['department']->name, $evaluations),
            'scores' => array_map(fn ($e) => $e['score'], $evaluations),
            'progress' => array_map(fn ($e) => $e['avg_progress'], $evaluations),
            'approval' => array_map(fn ($e) => $e['approval_rate'], $evaluations),
            'budget' => array_map(fn ($e) => $e['budget_score'], $evaluations),
        ];

        return view('evaluation.index', compact('year', 'evaluations', 'municipalityAvg', 'chartData'));
    }
}
