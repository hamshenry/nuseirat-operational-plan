<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\MonthlyFollowup;
use App\Models\PlanningYear;
use App\Models\RiskObstacle;
use Mpdf\Mpdf;

class PdfExportService
{
    public function __construct(private EvaluationService $evaluationService)
    {
    }

    public function generateReportPdf(string $type, PlanningYear $year, ?int $departmentId = null): string
    {
        $department = $departmentId ? Department::find($departmentId) : null;
        $logoBase64 = '';
        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $data = [
            'year' => $year,
            'department' => $department,
            'logoBase64' => $logoBase64,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'type' => $type,
        ];

        if ($type === 'plan') {
            $q = Activity::with(['mainObjective', 'subObjective', 'program', 'department'])
                ->where('planning_year_id', $year->id)
                ->orderBy('main_objective_id')
                ->orderBy('sub_objective_id')
                ->orderBy('program_id')
                ->orderBy('seq_number');
            if ($departmentId) {
                $q->where('department_id', $departmentId);
            }
            $data['title'] = "الخطة التشغيلية للعام {$year->year}";
            $data['activities'] = $q->get();
        } elseif ($type === 'evaluation') {
            $data['title'] = "تقرير تقييم أداء الدوائر للعام {$year->year}";
            $data['evaluations'] = $this->evaluationService->evaluateYear($year, $departmentId);
        } elseif ($type === 'budgets') {
            $data['title'] = "تقرير التكاليف والموازنات للعام {$year->year}";
            $data['evaluations'] = $this->evaluationService->evaluateYear($year, $departmentId);
            $data['allocations'] = BudgetAllocation::where('planning_year_id', $year->id)->get()->keyBy('department_id');
        } elseif ($type === 'monthly') {
            $q = MonthlyFollowup::with(['activity.department'])
                ->where('planning_year_id', $year->id)
                ->orderBy('month')
                ->orderBy('activity_id');
            if ($departmentId) {
                $q->whereHas('activity', fn ($aq) => $aq->where('department_id', $departmentId));
            }
            $data['title'] = "تقرير المتابعة الشهرية المتقدمة للعام {$year->year}";
            $data['followups'] = $q->get();
        } else {
            $q = RiskObstacle::with(['department', 'activity'])
                ->where('planning_year_id', $year->id)
                ->orderByDesc('risk_score');
            if ($departmentId) {
                $q->where('department_id', $departmentId);
            }
            $data['title'] = "تقرير إدارة المخاطر والمعوقات للعام {$year->year}";
            $data['risks'] = $q->get();
        }

        $html = view('pdf.report', $data)->render();

        $tempDir = storage_path('app/mpdf_tmp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font' => 'xbriyaz',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_top' => 12,
            'margin_bottom' => 14,
            'margin_left' => 10,
            'margin_right' => 10,
            'tempDir' => $tempDir,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:8.5pt;color:#475569;border-top:1px solid #cbd5e1;padding-top:4px;">بلدية النصيرات — نظام إدارة الخطة التشغيلية — صفحة {PAGENO} من {nbpg}</div>');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
