<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\MonthlyFollowup;
use App\Models\PlanningYear;
use App\Models\RiskObstacle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExportService
{
    public function __construct(private EvaluationService $evaluationService)
    {
    }

    /**
     * Export full municipality (or single department) operational plan matching the official Palestinian municipal template.
     */
    public function exportOperationalPlan(PlanningYear $year, ?int $departmentId = null): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('الخطة التشغيلية');
        $sheet->setRightToLeft(true);

        $this->addOfficialHeader($sheet, $year, $departmentId, 19);

        $headers = [
            'م / رقم النشاط',
            'الهدف الرئيسي',
            'الهدف الفرعي',
            'البرنامج',
            'النشاط',
            'مؤشر الأداء',
            'قيمة المؤشر',
            'وسائل التحقق',
            'الجهة المسئولة',
            'الجهة المساندة (داخلي)',
            'الجهة المساندة (خارجي)',
            'ر1',
            'ر2',
            'ر3',
            'ر4',
            'الأهمية',
            'التكلفة التقديرية',
            'الدائرة',
            'الحالة',
        ];

        $headerRow = 6;
        foreach ($headers as $idx => $title) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $title);
        }

        $sheet->getStyle("A{$headerRow}:S{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F3A4A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        $query = Activity::with(['mainObjective', 'subObjective', 'program', 'department'])
            ->where('planning_year_id', $year->id)
            ->orderBy('main_objective_id')
            ->orderBy('sub_objective_id')
            ->orderBy('program_id')
            ->orderBy('seq_number');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $activities = $query->get();

        $row = $headerRow + 1;
        foreach ($activities as $i => $act) {
            $sheet->setCellValue("A{$row}", $act->activity_code);
            $sheet->setCellValue("B{$row}", ($act->mainObjective?->code ? $act->mainObjective->code . ' - ' : '') . ($act->mainObjective?->title ?? ''));
            $sheet->setCellValue("C{$row}", ($act->subObjective?->code ? $act->subObjective->code . ' - ' : '') . ($act->subObjective?->title ?? ''));
            $sheet->setCellValue("D{$row}", ($act->program?->code ? $act->program->code . ' - ' : '') . ($act->program?->title ?? ''));
            $sheet->setCellValue("E{$row}", $act->title);
            $sheet->setCellValue("F{$row}", $act->kpi_name);
            $sheet->setCellValue("G{$row}", $act->kpi_target);
            $sheet->setCellValue("H{$row}", $act->verification_means);
            $sheet->setCellValue("I{$row}", $act->responsible_entity);
            $sheet->setCellValue("J{$row}", $act->support_internal ?: '—');
            $sheet->setCellValue("K{$row}", $act->support_external ?: '—');
            $sheet->setCellValue("L{$row}", $act->q1 ? '✓' : '');
            $sheet->setCellValue("M{$row}", $act->q2 ? '✓' : '');
            $sheet->setCellValue("N{$row}", $act->q3 ? '✓' : '');
            $sheet->setCellValue("O{$row}", $act->q4 ? '✓' : '');
            $sheet->setCellValue("P{$row}", $act->priorityLabel());
            $sheet->setCellValue("Q{$row}", (float) $act->estimated_cost);
            $sheet->setCellValue("R{$row}", $act->department?->name ?? '');
            $sheet->setCellValue("S{$row}", $act->statusLabel());

            $bg = ($i % 2 === 1) ? 'F8FAFC' : 'FFFFFF';
            $sheet->getStyle("A{$row}:S{$row}")->applyFromArray([
                'font' => ['size' => 10, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_RIGHT, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);
            foreach (['A', 'G', 'L', 'M', 'N', 'O', 'P', 'Q', 'S'] as $centerCol) {
                $sheet->getStyle("{$centerCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            foreach (['L', 'M', 'N', 'O'] as $qCol) {
                if ($sheet->getCell("{$qCol}{$row}")->getValue() === '✓') {
                    $sheet->getStyle("{$qCol}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '047857'], 'size' => 12],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
                    ]);
                }
            }
            $sheet->getStyle("Q{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getRowDimension($row)->setRowHeight(36);
            $row++;
        }

        // Totals Row
        $lastDataRow = max($headerRow + 1, $row - 1);
        $sheet->mergeCells("A{$row}:P{$row}");
        $sheet->setCellValue("A{$row}", "الإجمالي الكلي للأنشطة ({$activities->count()} نشاط) — إجمالي التكلفة التقديرية");
        $sheet->setCellValue("Q{$row}", $activities->count() > 0 ? "=SUM(Q7:Q{$lastDataRow})" : 0);
        $sheet->setCellValue("R{$row}", 'شيكل');
        $sheet->setCellValue("S{$row}", '');
        $sheet->getStyle("A{$row}:S{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial', 'color' => ['rgb' => '0F3A4A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0F3A4A']]],
        ]);
        $sheet->getStyle("Q{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getRowDimension($row)->setRowHeight(28);

        // Column widths
        $widths = [
            'A' => 14, 'B' => 26, 'C' => 24, 'D' => 24, 'E' => 34, 'F' => 24,
            'G' => 15, 'H' => 26, 'I' => 20, 'J' => 20, 'K' => 20,
            'L' => 6, 'M' => 6, 'N' => 6, 'O' => 6, 'P' => 13, 'Q' => 16, 'R' => 18, 'S' => 13,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $sheet->freezePane('A7');
        $sheet->setAutoFilter("A{$headerRow}:S{$lastDataRow}");
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        // Sheet 2: Department & Budget Summary
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('ملخص الدوائر والموازنة');
        $summarySheet->setRightToLeft(true);
        $this->populateDepartmentSummarySheet($summarySheet, $year, $departmentId);

        $spreadsheet->setActiveSheetIndex(0);
        return $this->writeTempXlsx($spreadsheet, "operational_plan_{$year->year}");
    }

    public function exportReport(string $type, PlanningYear $year, ?int $departmentId = null): string
    {
        if ($type === 'plan') {
            return $this->exportOperationalPlan($year, $departmentId);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);

        if ($type === 'evaluation') {
            $sheet->setTitle('تقييم أداء الدوائر');
            $this->addOfficialHeader($sheet, $year, $departmentId, 11, 'تقرير تقييم أداء الدوائر');
            $rows = $this->evaluationService->evaluateYear($year, $departmentId);
            $this->writeTable($sheet, 6, [
                'الترتيب', 'الدائرة', 'إجمالي الأنشطة', 'المعتمدة', 'متوسط الإنجاز %',
                'نسبة الاعتماد %', 'الالتزام بالجدول %', 'كفاءة الموازنة %', 'المتابعة والتوثيق %', 'الدرجة النهائية', 'التقدير',
            ], array_map(fn ($r) => [
                $r['rank'], $r['department']->name, $r['total_activities'], $r['approved'],
                $r['avg_progress'] . '%', $r['approval_rate'] . '%', $r['timeliness_rate'] . '%',
                $r['budget_score'] . '%', $r['doc_rate'] . '%', $r['score'], $r['grade'],
            ], $rows));
        } elseif ($type === 'budgets') {
            $sheet->setTitle('التكاليف والموازنات');
            $this->addOfficialHeader($sheet, $year, $departmentId, 9, 'تقرير إدارة التكاليف والموازنات');
            $rows = $this->evaluationService->evaluateYear($year, $departmentId);
            $allocs = BudgetAllocation::where('planning_year_id', $year->id)->get()->keyBy('department_id');
            $this->writeTable($sheet, 6, [
                'الدائرة', 'الموازنة المعتمدة', 'تمويل ذاتي', 'منح ومشاريع', 'تمويل خارجي',
                'التكلفة المخططة للأنشطة', 'الصرف الفعلي', 'المتبقي من الموازنة', 'نسبة الصرف %',
            ], array_map(function ($r) use ($allocs) {
                $al = $allocs->get($r['department']->id);
                $allocAmt = $r['allocated_budget'];
                $remaining = $allocAmt - $r['actual_spent'];
                $pct = $allocAmt > 0 ? round(($r['actual_spent'] / $allocAmt) * 100, 1) : 0;
                return [
                    $r['department']->name,
                    $allocAmt,
                    (float) ($al?->self_funded ?? 0),
                    (float) ($al?->grants_funded ?? 0),
                    (float) ($al?->external_funded ?? 0),
                    $r['planned_cost'],
                    $r['actual_spent'],
                    $remaining,
                    $pct . '%',
                ];
            }, $rows));
        } elseif ($type === 'monthly') {
            $sheet->setTitle('المتابعة الشهرية');
            $this->addOfficialHeader($sheet, $year, $departmentId, 10, 'تقرير المتابعة الشهرية المتقدمة');
            $q = MonthlyFollowup::with(['activity.department'])
                ->where('planning_year_id', $year->id)
                ->orderBy('month')
                ->orderBy('activity_id');
            if ($departmentId) {
                $q->whereHas('activity', fn ($aq) => $aq->where('department_id', $departmentId));
            }
            $items = $q->get();
            $this->writeTable($sheet, 6, [
                'الشهر', 'الدائرة', 'رقم النشاط', 'النشاط', 'المخطط %', 'الفعلي %',
                'القيمة المتحققة', 'الصرف الفعلي', 'حالة التنفيذ', 'أبرز الإنجازات / المعوقات',
            ], $items->map(fn ($f) => [
                MonthlyFollowup::monthName($f->month),
                $f->activity?->department?->name ?? '',
                $f->activity?->activity_code ?? '',
                $f->activity?->title ?? '',
                $f->planned_percent . '%',
                $f->actual_percent . '%',
                $f->achieved_value ?: '—',
                (float) $f->actual_cost,
                $f->executionStatusLabel(),
                trim(($f->achievements_note ?: '') . ($f->obstacles_note ? ' | معوقات: ' . $f->obstacles_note : '')),
            ])->all());
        } else {
            // risks
            $sheet->setTitle('المخاطر والمعوقات');
            $this->addOfficialHeader($sheet, $year, $departmentId, 10, 'تقرير سجل المخاطر والمعوقات');
            $q = RiskObstacle::with(['department', 'activity'])
                ->where('planning_year_id', $year->id)
                ->orderByDesc('risk_score');
            if ($departmentId) {
                $q->where('department_id', $departmentId);
            }
            $risks = $q->get();
            $this->writeTable($sheet, 6, [
                'الدائرة', 'النشاط المرتبط', 'عنوان الخطر / المعوق', 'التصنيف',
                'الاحتمالية', 'الأثر', 'الدرجة', 'مستوى الخطورة', 'خطة المعالجة', 'الحالة',
            ], $risks->map(fn ($r) => [
                $r->department?->name ?? '',
                $r->activity ? ($r->activity->activity_code . ' - ' . $r->activity->title) : 'عام',
                $r->title,
                $r->categoryLabel(),
                $r->likelihood,
                $r->impact,
                $r->risk_score,
                $r->severityLabel(),
                $r->mitigation_plan ?: '—',
                $r->statusLabel(),
            ])->all());
        }

        return $this->writeTempXlsx($spreadsheet, "report_{$type}_{$year->year}");
    }

    private function populateDepartmentSummarySheet($sheet, PlanningYear $year, ?int $departmentId): void
    {
        $this->addOfficialHeader($sheet, $year, $departmentId, 10, 'ملخص الدوائر والموازنة التقديرية');
        $rows = $this->evaluationService->evaluateYear($year, $departmentId);
        $this->writeTable($sheet, 6, [
            'الدائرة', 'إجمالي الأنشطة', 'معتمد', 'قيد المراجعة', 'مرفوض', 'مسودة',
            'الموازنة المعتمدة', 'التكلفة المخططة', 'الصرف الفعلي', 'متوسط الإنجاز %',
        ], array_map(fn ($r) => [
            $r['department']->name,
            $r['total_activities'],
            $r['approved'],
            $r['pending'],
            $r['rejected'],
            $r['draft'],
            $r['allocated_budget'],
            $r['planned_cost'],
            $r['actual_spent'],
            $r['avg_progress'] . '%',
        ], $rows));
    }

    private function addOfficialHeader($sheet, PlanningYear $year, ?int $departmentId, int $colCount, ?string $customTitle = null): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $deptName = $departmentId ? (' — ' . (Department::find($departmentId)?->name ?? '')) : ' — جميع الدوائر';

        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $drawing = new Drawing();
            $drawing->setName('شعار بلدية النصيرات');
            $drawing->setPath($logoPath);
            $drawing->setHeight(68);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(8);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        }

        $sheet->mergeCells("B1:{$lastCol}1");
        $sheet->setCellValue('B1', 'دولة فلسطين — وزارة الحكم المحلي — بلدية النصيرات');
        $sheet->mergeCells("B2:{$lastCol}2");
        $sheet->setCellValue('B2', ($customTitle ?? "الخطة التشغيلية للعام {$year->year}") . $deptName);
        $sheet->mergeCells("B3:{$lastCol}3");
        $sheet->setCellValue('B3', 'تاريخ الإصدار: ' . now()->format('Y-m-d H:i') . ' | نظام إدارة الخطة التشغيلية - بلدية النصيرات');

        $sheet->getStyle("B1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'name' => 'Arial', 'color' => ['rgb' => '0F3A4A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B2:{$lastCol}2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial', 'color' => ['rgb' => '047857']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B3:{$lastCol}3")->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial', 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(18);
        $sheet->getRowDimension(4)->setRowHeight(10);
    }

    private function writeTable($sheet, int $startRow, array $headers, array $dataRows): void
    {
        $colCount = count($headers);
        $lastCol = Coordinate::stringFromColumnIndex($colCount);

        foreach ($headers as $idx => $h) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$col}{$startRow}", $h);
            $sheet->getColumnDimension($col)->setWidth(19);
        }
        $sheet->getStyle("A{$startRow}:{$lastCol}{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F3A4A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getRowDimension($startRow)->setRowHeight(28);

        $r = $startRow + 1;
        foreach ($dataRows as $i => $rowValues) {
            foreach ($rowValues as $idx => $val) {
                $col = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->setCellValue("{$col}{$r}", $val);
                if (is_float($val)) {
                    $sheet->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }
            $bg = ($i % 2 === 1) ? 'F8FAFC' : 'FFFFFF';
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                'font' => ['size' => 10, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(26);
            $r++;
        }

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
    }

    private function writeTempXlsx(Spreadsheet $spreadsheet, string $prefix): string
    {
        $dir = storage_path('app/exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $prefix . '_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        return $path;
    }
}
