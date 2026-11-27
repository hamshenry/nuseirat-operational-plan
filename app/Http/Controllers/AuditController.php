<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type'));
        }
        if ($request->filled('q')) {
            $kw = '%' . trim($request->string('q')->toString()) . '%';
            $query->where(function ($sub) use ($kw) {
                $sub->where('username', 'like', $kw)
                    ->orWhere('full_name', 'like', $kw)
                    ->orWhere('entity_label', 'like', $kw)
                    ->orWhere('details_json', 'like', $kw);
            });
        }

        $logs = $query->paginate(30)->withQueryString();
        $versionedActivities = Activity::with('department')
            ->withCount('versions')
            ->where('current_version', '>', 1)
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get();

        return view('audit.index', compact('logs', 'versionedActivities'));
    }
}
