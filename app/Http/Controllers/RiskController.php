<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Department;
use App\Models\RiskObstacle;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RiskController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear($request->integer('year_id') ?: null);

        $query = RiskObstacle::with(['department', 'activity'])
            ->where('planning_year_id', $year->id);

        if ($user->isDepartment()) {
            $query->where('department_id', $user->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $risks = $query->orderByDesc('risk_score')->orderByDesc('id')->get();

        // Build 5x5 matrix counts [likelihood 1..5][impact 1..5]
        $matrix = [];
        for ($l = 5; $l >= 1; $l--) {
            for ($i = 1; $i <= 5; $i++) {
                $matrix[$l][$i] = $risks->where('likelihood', $l)->where('impact', $i)->count();
            }
        }

        $departments = Department::orderBy('code')->get();
        $activities = Activity::where('planning_year_id', $year->id)
            ->when($user->isDepartment(), fn ($q) => $q->where('department_id', $user->department_id))
            ->orderBy('activity_code')
            ->get();

        return view('risks.index', compact('year', 'risks', 'matrix', 'departments', 'activities'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear();

        $validated = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'activity_id' => ['nullable', 'exists:activities,id'],
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'in:financial,technical,administrative,external,legal'],
            'likelihood' => ['required', 'integer', 'min:1', 'max:5'],
            'impact' => ['required', 'integer', 'min:1', 'max:5'],
            'mitigation_plan' => ['nullable', 'string', 'max:2000'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:open,mitigating,resolved,escalated'],
        ]);

        $deptId = $user->isDepartment() ? $user->department_id : (int) ($validated['department_id'] ?? 0);
        if (!$deptId) {
            return back()->withErrors(['department_id' => 'يرجى تحديد الدائرة.']);
        }

        [$score, $severity] = AuditService::calcRiskSeverity((int) $validated['likelihood'], (int) $validated['impact']);

        $risk = RiskObstacle::create([
            'planning_year_id' => $year->id,
            'department_id' => $deptId,
            'activity_id' => ($validated['activity_id'] ?? null) ?: null,
            'title' => trim($validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')),
            'category' => $validated['category'],
            'likelihood' => (int) $validated['likelihood'],
            'impact' => (int) $validated['impact'],
            'risk_score' => $score,
            'severity' => $severity,
            'mitigation_plan' => trim((string) ($validated['mitigation_plan'] ?? '')),
            'owner_name' => trim((string) ($validated['owner_name'] ?? '')),
            'status' => $validated['status'],
            'created_by_id' => $user->id,
        ]);

        AuditService::log('create', 'risk_obstacle', $risk->id, $risk->title, ['severity' => $severity, 'score' => $score]);

        if (in_array($severity, ['critical', 'high'], true) || $risk->status === 'escalated') {
            AuditService::notifyAdmins(
                'تسجيل خطر/معوق مرتفع الأهمية',
                "سُجل خطر «{$risk->title}» بمستوى ({$risk->severityLabel()} — {$score}/25).",
                'risk',
                route('risks.index'),
                $user->id
            );
        }

        return back()->with('success', 'تم إضافة الخطر / المعوق إلى السجل بنجاح.');
    }

    public function update(Request $request, RiskObstacle $risk): RedirectResponse
    {
        $user = Auth::user();
        if ($user->isDepartment() && (int) $risk->department_id !== (int) $user->department_id) {
            abort(403);
        }

        $validated = $request->validate([
            'activity_id' => ['nullable', 'exists:activities,id'],
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'in:financial,technical,administrative,external,legal'],
            'likelihood' => ['required', 'integer', 'min:1', 'max:5'],
            'impact' => ['required', 'integer', 'min:1', 'max:5'],
            'mitigation_plan' => ['nullable', 'string', 'max:2000'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:open,mitigating,resolved,escalated'],
        ]);

        [$score, $severity] = AuditService::calcRiskSeverity((int) $validated['likelihood'], (int) $validated['impact']);

        $risk->update([
            'activity_id' => ($validated['activity_id'] ?? null) ?: null,
            'title' => trim($validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')),
            'category' => $validated['category'],
            'likelihood' => (int) $validated['likelihood'],
            'impact' => (int) $validated['impact'],
            'risk_score' => $score,
            'severity' => $severity,
            'mitigation_plan' => trim((string) ($validated['mitigation_plan'] ?? '')),
            'owner_name' => trim((string) ($validated['owner_name'] ?? '')),
            'status' => $validated['status'],
        ]);

        AuditService::log('update', 'risk_obstacle', $risk->id, $risk->title);
        return back()->with('success', 'تم تحديث بيانات الخطر / المعوق بنجاح.');
    }

    public function destroy(RiskObstacle $risk): RedirectResponse
    {
        $user = Auth::user();
        if ($user->isDepartment() && (int) $risk->department_id !== (int) $user->department_id) {
            abort(403);
        }
        $risk->update(['deleted_by_id' => $user->id]);
        $risk->delete();
        AuditService::log('delete', 'risk_obstacle', $risk->id, $risk->title);
        return back()->with('success', 'تم نقل الخطر إلى سلة المحذوفات.');
    }
}
