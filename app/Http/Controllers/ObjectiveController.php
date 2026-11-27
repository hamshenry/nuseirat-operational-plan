<?php

namespace App\Http\Controllers;

use App\Models\MainObjective;
use App\Models\Program;
use App\Models\SubObjective;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ObjectiveController extends Controller
{
    public function index(Request $request): View
    {
        $year = AuditService::getSelectedYear($request->integer('year_id') ?: null);
        $mainObjectives = MainObjective::with(['subObjectives.programs'])
            ->where('planning_year_id', $year->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('objectives.index', compact('year', 'mainObjectives'));
    }

    public function storeMain(Request $request): RedirectResponse
    {
        $year = AuditService::getSelectedYear();
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $nextOrder = (int) (MainObjective::where('planning_year_id', $year->id)->max('sort_order') ?? 0) + 1;
        $code = trim($validated['code'] ?? '') ?: (string) $nextOrder;

        $obj = MainObjective::create([
            'planning_year_id' => $year->id,
            'code' => $code,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? '',
            'sort_order' => $nextOrder,
        ]);

        AuditService::log('create', 'main_objective', $obj->id, "{$obj->code} - {$obj->title}");
        return back()->with('success', 'تم إضافة الهدف الرئيسي بنجاح.');
    }

    public function updateMain(Request $request, MainObjective $mainObjective): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $mainObjective->update($validated);
        AuditService::log('update', 'main_objective', $mainObjective->id, $mainObjective->title);
        return back()->with('success', 'تم تعديل الهدف الرئيسي بنجاح.');
    }

    public function destroyMain(MainObjective $mainObjective): RedirectResponse
    {
        $mainObjective->update(['deleted_by_id' => Auth::id()]);
        $mainObjective->delete();
        AuditService::log('delete', 'main_objective', $mainObjective->id, $mainObjective->title);
        return back()->with('success', 'تم نقل الهدف الرئيسي إلى سلة المحذوفات.');
    }

    public function storeSub(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'main_objective_id' => ['required', 'exists:main_objectives,id'],
            'code' => ['nullable', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $main = MainObjective::findOrFail($validated['main_objective_id']);
        $nextOrder = (int) (SubObjective::where('main_objective_id', $main->id)->max('sort_order') ?? 0) + 1;
        $code = trim($validated['code'] ?? '') ?: "{$main->code}.{$nextOrder}";

        $sub = SubObjective::create([
            'main_objective_id' => $main->id,
            'code' => $code,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? '',
            'sort_order' => $nextOrder,
        ]);

        AuditService::log('create', 'sub_objective', $sub->id, "{$sub->code} - {$sub->title}");
        return back()->with('success', 'تم إضافة الهدف الفرعي بنجاح.');
    }

    public function updateSub(Request $request, SubObjective $subObjective): RedirectResponse
    {
        $validated = $request->validate([
            'main_objective_id' => ['required', 'exists:main_objectives,id'],
            'code' => ['required', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $subObjective->update($validated);
        AuditService::log('update', 'sub_objective', $subObjective->id, $subObjective->title);
        return back()->with('success', 'تم تعديل الهدف الفرعي بنجاح.');
    }

    public function destroySub(SubObjective $subObjective): RedirectResponse
    {
        $subObjective->update(['deleted_by_id' => Auth::id()]);
        $subObjective->delete();
        AuditService::log('delete', 'sub_objective', $subObjective->id, $subObjective->title);
        return back()->with('success', 'تم نقل الهدف الفرعي إلى سلة المحذوفات.');
    }

    public function storeProgram(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sub_objective_id' => ['required', 'exists:sub_objectives,id'],
            'code' => ['nullable', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $sub = SubObjective::findOrFail($validated['sub_objective_id']);
        $nextOrder = (int) (Program::where('sub_objective_id', $sub->id)->max('sort_order') ?? 0) + 1;
        $code = trim($validated['code'] ?? '') ?: "{$sub->code}.{$nextOrder}";

        $prog = Program::create([
            'sub_objective_id' => $sub->id,
            'code' => $code,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? '',
            'sort_order' => $nextOrder,
        ]);

        AuditService::log('create', 'program', $prog->id, "{$prog->code} - {$prog->title}");
        return back()->with('success', 'تم إضافة البرنامج بنجاح.');
    }

    public function updateProgram(Request $request, Program $program): RedirectResponse
    {
        $validated = $request->validate([
            'sub_objective_id' => ['required', 'exists:sub_objectives,id'],
            'code' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $program->update($validated);
        AuditService::log('update', 'program', $program->id, $program->title);
        return back()->with('success', 'تم تعديل البرنامج بنجاح.');
    }

    public function destroyProgram(Program $program): RedirectResponse
    {
        $program->update(['deleted_by_id' => Auth::id()]);
        $program->delete();
        AuditService::log('delete', 'program', $program->id, $program->title);
        return back()->with('success', 'تم نقل البرنامج إلى سلة المحذوفات.');
    }

    // Cascading AJAX JSON endpoints for Department Activity Forms & Advanced Filters
    public function apiSubObjectives(int $mainObjectiveId): JsonResponse
    {
        $subs = SubObjective::where('main_objective_id', $mainObjectiveId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'title']);
        return response()->json($subs);
    }

    public function apiPrograms(int $subObjectiveId): JsonResponse
    {
        $programs = Program::where('sub_objective_id', $subObjectiveId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'title']);
        return response()->json($programs);
    }
}
