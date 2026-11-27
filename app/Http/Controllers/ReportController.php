<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\AuditService;
use App\Services\ExcelExportService;
use App\Services\PdfExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private ExcelExportService $excelExport,
        private PdfExportService $pdfExport
    ) {
    }

    public function index(Request $request): View
    {
        $year = AuditService::getSelectedYear($request->integer('year_id') ?: null);
        $departments = Department::orderBy('code')->get();
        return view('reports.index', compact('year', 'departments'));
    }

    public function exportExcel(Request $request, string $type): BinaryFileResponse
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear($request->integer('year_id') ?: null);
        $deptId = $user->isDepartment()
            ? $user->department_id
            : ($request->filled('department_id') ? $request->integer('department_id') : null);

        $allowed = ['plan', 'evaluation', 'budgets', 'monthly', 'risks'];
        if (!in_array($type, $allowed, true)) {
            abort(404);
        }

        $filePath = $this->excelExport->exportReport($type, $year, $deptId);
        AuditService::log('export', 'report', null, "تصدير Excel ({$type}) - {$year->year}", ['department_id' => $deptId]);

        $downloadName = "nuseirat_{$type}_{$year->year}.xlsx";
        return response()->download($filePath, $downloadName)->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request, string $type): Response
    {
        $user = Auth::user();
        $year = AuditService::getSelectedYear($request->integer('year_id') ?: null);
        $deptId = $user->isDepartment()
            ? $user->department_id
            : ($request->filled('department_id') ? $request->integer('department_id') : null);

        $allowed = ['plan', 'evaluation', 'budgets', 'monthly', 'risks'];
        if (!in_array($type, $allowed, true)) {
            abort(404);
        }

        $pdfBinary = $this->pdfExport->generateReportPdf($type, $year, $deptId);
        AuditService::log('export', 'report', null, "تصدير PDF ({$type}) - {$year->year}", ['department_id' => $deptId]);

        $downloadName = "nuseirat_{$type}_{$year->year}.pdf";
        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$downloadName}\"",
        ]);
    }
}
