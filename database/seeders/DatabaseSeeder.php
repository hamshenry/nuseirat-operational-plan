<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\InternalMessage;
use App\Models\MainObjective;
use App\Models\MonthlyFollowup;
use App\Models\OperationalPlan;
use App\Models\PlanningYear;
use App\Models\Program;
use App\Models\RiskObstacle;
use App\Models\SubObjective;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::withTrashed()->count() > 0) {
            return;
        }

        // 1. General Supervisor (Super Admin)
        $admin = User::create([
            'username' => 'admin',
            'name' => 'المشرف العام - بلدية النصيرات',
            'email' => 'admin@nuseirat.ps',
            'password' => Hash::make('Admin@1234'),
            'role' => 'admin',
            'department_id' => null,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        // 2. Planning Years (2026 historical + 2027 active)
        $y2026 = PlanningYear::create([
            'year' => 2026,
            'title' => 'الخطة التشغيلية لبلدية النصيرات للعام 2026',
            'is_active' => false,
            'is_entry_open' => false,
            'entry_start_date' => '2025-11-01',
            'entry_end_date' => '2026-01-31',
            'notes' => 'خطة العام السابق — متاحة للمراجعة والنسخ للسنوات الجديدة.',
        ]);

        $y2027 = PlanningYear::create([
            'year' => 2027,
            'title' => 'الخطة التشغيلية لبلدية النصيرات للعام 2027',
            'is_active' => true,
            'is_entry_open' => true,
            'entry_start_date' => '2026-10-01',
            'entry_end_date' => '2027-12-31',
            'notes' => 'السنة التخطيطية النشطة المفتوحة لإدخال ومتابعة خطط الدوائر.',
        ]);

        // 3. Departments + Department Accounts (must_change_password = true on first login!)
        $deptSpecs = [
            ['D01', 'الدائرة القانونية', 'أ. محمود العطار', 'legal', 'رئيس الدائرة القانونية', 85000],
            ['D02', 'الشؤون الإدارية', 'أ. سارة أبو زايد', 'admin_dept', 'مدير الشؤون الإدارية', 145000],
            ['D03', 'الهندسة والمشاريع', 'م. خالد النباهين', 'engineering', 'مدير الهندسة والمشاريع', 620000],
            ['D04', 'الدائرة المالية', 'أ. أحمد درويش', 'finance', 'المدير المالي', 110000],
            ['D05', 'الصحة والبيئة', 'م. هبة حمدان', 'health', 'مدير الصحة والبيئة', 290000],
        ];

        $departments = [];
        $deptUsers = [];
        foreach ($deptSpecs as [$code, $name, $mgr, $uname, $ufull, $budgetAmt]) {
            $dept = Department::create([
                'code' => $code,
                'name' => $name,
                'manager_name' => $mgr,
                'description' => "تخطيط وتنفيذ ومتابعة أنشطة ومشاريع {$name} في بلدية النصيرات.",
                'email' => "{$uname}@nuseirat.ps",
                'phone' => '08-2550000',
            ]);
            $departments[$code] = $dept;

            $u = User::create([
                'username' => $uname,
                'name' => $ufull,
                'email' => "{$uname}@nuseirat.ps",
                'password' => Hash::make('Dept@1234'),
                'role' => 'department',
                'department_id' => $dept->id,
                'must_change_password' => true,
                'is_active' => true,
            ]);
            $deptUsers[$code] = $u;

            BudgetAllocation::create([
                'planning_year_id' => $y2027->id,
                'department_id' => $dept->id,
                'allocated_amount' => $budgetAmt,
                'self_funded' => round($budgetAmt * 0.55, 2),
                'grants_funded' => round($budgetAmt * 0.30, 2),
                'external_funded' => round($budgetAmt * 0.15, 2),
                'notes' => "الموازنة المعتمدة لدائرة {$name} لعام 2027",
                'updated_by_id' => $admin->id,
            ]);
        }

        // 4. Strategic Hierarchy 2027 (Main -> Sub -> Program)
        $tree = [
            ['1', 'تعزيز الحوكمة الرشيدة وتطوير الأداء المؤسسي', [
                ['1.1', 'تطوير الإطار القانوني والإداري والرقابي', [
                    ['1.1.1', 'برنامج تحديث الأنظمة واللوائح البلدية'],
                    ['1.1.2', 'برنامج تنمية القدرات البشرية والتدريب'],
                ]],
                ['1.2', 'التحول الرقمي وتطوير الخدمات الإلكترونية', [
                    ['1.2.1', 'برنامج أتمتة خدمات الجمهور والأرشفة'],
                    ['1.2.2', 'برنامج تعزيز الشفافية والمساءلة المجتمعية'],
                ]],
            ]],
            ['2', 'تطوير البنية التحتية والخدمات الأساسية المستدامة', [
                ['2.1', 'تأهيل وتطوير شبكة الطرق والمياه والصرف الصحي', [
                    ['2.1.1', 'برنامج صيانة وتعبيد الشوارع الرئيسية والفرعية'],
                    ['2.1.2', 'برنامج تحسين كفاءة شبكات المياه وتقليل الفاقد'],
                ]],
                ['2.2', 'تنمية الإيرادات والاستدامة المالية', [
                    ['2.2.1', 'برنامج تطوير الجباية وتحصيل الرسوم البلدية'],
                ]],
            ]],
            ['3', 'تحسين الصحة العامة والبيئة وجودة الحياة', [
                ['3.1', 'إدارة النفايات الصلبة والنظافة العامة', [
                    ['3.1.1', 'برنامج رفع كفاءة جمع وترحيل النفايات'],
                    ['3.1.2', 'برنامج التوعية البيئية والتشجير الحضري'],
                ]],
            ]],
        ];

        $progMap = [];
        foreach ($tree as $mIdx => [$mCode, $mTitle, $subs]) {
            $mo = MainObjective::create([
                'planning_year_id' => $y2027->id,
                'code' => $mCode,
                'title' => $mTitle,
                'sort_order' => $mIdx + 1,
            ]);
            foreach ($subs as $sIdx => [$sCode, $sTitle, $progs]) {
                $so = SubObjective::create([
                    'main_objective_id' => $mo->id,
                    'code' => $sCode,
                    'title' => $sTitle,
                    'sort_order' => $sIdx + 1,
                ]);
                foreach ($progs as $pIdx => [$pCode, $pTitle]) {
                    $pr = Program::create([
                        'sub_objective_id' => $so->id,
                        'code' => $pCode,
                        'title' => $pTitle,
                        'sort_order' => $pIdx + 1,
                    ]);
                    $progMap[$pCode] = [$mo, $so, $pr];
                }
            }
        }

        // Seed 2026 sample hierarchy & plan for Clone Year testing
        $mo26 = MainObjective::create(['planning_year_id' => $y2026->id, 'code' => '1', 'title' => 'تطوير الأداء المؤسسي والخدمات (2026)', 'sort_order' => 1]);
        $so26 = SubObjective::create(['main_objective_id' => $mo26->id, 'code' => '1.1', 'title' => 'تحسين جودة خدمات الجمهور', 'sort_order' => 1]);
        $pr26 = Program::create(['sub_objective_id' => $so26->id, 'code' => '1.1.1', 'title' => 'برنامج الأرشفة وخدمة الجمهور', 'sort_order' => 1]);

        $plan26 = OperationalPlan::create([
            'planning_year_id' => $y2026->id,
            'department_id' => $departments['D02']->id,
            'title' => 'الخطة التشغيلية — الشؤون الإدارية 2026',
            'status' => 'approved',
            'approved_at' => now()->subMonths(6),
        ]);
        [$c26, $s26] = AuditService::generateActivityCode($pr26->id, $y2026->id);
        $a26 = Activity::create([
            'operational_plan_id' => $plan26->id,
            'planning_year_id' => $y2026->id,
            'department_id' => $departments['D02']->id,
            'main_objective_id' => $mo26->id,
            'sub_objective_id' => $so26->id,
            'program_id' => $pr26->id,
            'activity_code' => $c26,
            'seq_number' => $s26,
            'title' => 'أرشفة ملفات الموظفين إلكترونياً بنسبة 100%',
            'kpi_name' => 'نسبة الملفات المؤرشفة إلكترونياً',
            'kpi_target' => '100%',
            'kpi_target_numeric' => 100,
            'verification_means' => 'تقرير نظام الأرشفة + كشوفات التدقيق',
            'responsible_entity' => 'قسم شؤون الموظفين',
            'support_type' => 'internal',
            'support_internal' => 'وحدة تكنولوجيا المعلومات',
            'q1' => true, 'q2' => true, 'q3' => false, 'q4' => false,
            'priority' => 'high',
            'estimated_cost' => 18000,
            'funding_source' => 'تمويل ذاتي',
            'status' => 'approved',
            'reviewed_by_id' => $admin->id,
            'reviewed_at' => now()->subMonths(6),
            'created_by_id' => $deptUsers['D02']->id,
        ]);
        AuditService::saveActivityVersion($a26, 'النسخة الأولية 2026', $deptUsers['D02']);

        // 5. Operational Plans 2027
        $plans = [];
        foreach ($departments as $code => $dept) {
            $plans[$code] = OperationalPlan::create([
                'planning_year_id' => $y2027->id,
                'department_id' => $dept->id,
                'title' => "الخطة التشغيلية — {$dept->name} 2027",
                'status' => 'draft',
            ]);
        }

        // 6. Activities 2027
        $samples = [
            ['D01', '1.1.1', 'مراجعة وتحديث لائحة الحرف والصناعات والأسواق المحلية', 'عدد اللوائح المحدثة والمصادق عليها', '3 لوائح', 'محاضر جلسات المجلس البلدي واللوائح المنشورة', 'الدائرة القانونية', 'both', 'دائرة التنظيم، الدائرة المالية', 'وزارة الحكم المحلي', true, true, false, false, 'high', 15000, 'تمويل ذاتي', 'approved', 'تمت المراجعة والاعتماد وفق الأولويات الاستراتيجية.'],
            ['D01', '1.2.2', 'إعداد دليل إجراءات التعاقد والعطاءات البلدية وتعميمه', 'إصدار دليل إجراءات معتمد', '1 دليل', 'نسخة الدليل المعتمدة + محضر التعميم', 'قسم العقود والاستشارات', 'internal', 'وحدة العطاءات، الشؤون الإدارية', '', false, true, true, false, 'medium', 12000, 'تمويل ذاتي', 'draft', ''],
            ['D02', '1.1.2', 'تنفيذ برنامج تدريبي متخصص لبناء قدرات موظفي خدمة الجمهور', 'عدد الموظفين المدربين', '45 موظف', 'كشوفات الحضور وشهادات التدريب وتقييم الدورة', 'قسم التدريب والتطوير', 'both', 'مركز خدمة الجمهور', 'معهد التنمية المحلية', true, true, true, false, 'high', 32000, 'منح ومشاريع', 'pending', ''],
            ['D02', '1.2.1', 'تطوير بوابة الطلبات الإلكترونية وربطها بنظام الأرشفة المركزي', 'عدد الخدمات المؤتمتة', '12 خدمة', 'تقرير الإطلاق الفعلي + سجل الطلبات الإلكترونية', 'وحدة تكنولوجيا المعلومات', 'internal', 'كافة الدوائر', '', true, true, true, true, 'critical', 68000, 'تمويل مشترك', 'approved', 'أولوية قصوى للتحول الرقمي.'],
            ['D03', '2.1.1', 'تأهيل وصيانة شارع صلاح الدين والشوارع الرابطة في النصيرات', 'طول الطرق المؤهلة (كم)', '4.5 كم', 'محاضر الاستلام الابتدائي والنهائي + صور ميدانية + فحوصات مخبرية', 'قسم الطرق والمشاريع', 'external', '', 'صندوق تطوير وإقراض الهيئات المحلية (MDLF)', true, true, true, false, 'critical', 340000, 'منح ومشاريع', 'approved', 'مشروع استراتيجي ممول ومعتمد.'],
            ['D03', '2.1.2', 'تركيب عدادات مياه ذكية واستبدال الخطوط المتهالكة في المنطقة الغربية', 'نسبة خفض فاقد المياه', '18%', 'تقارير قراءات العدادات الشهرية + كشوفات التركيب', 'قسم المياه والصرف الصحي', 'both', 'الدائرة المالية', 'سلطة المياه الفلسطينية', false, true, true, true, 'critical', 195000, 'تمويل خارجي', 'pending', ''],
            ['D04', '2.2.1', 'إطلاق حملة تسوية المتأخرات وتفعيل الدفع الإلكتروني للرسوم البلدية', 'نسبة نمو التحصيل المالي', '22%', 'التقارير المالية الربع سنوية + كشوفات بوابة الدفع', 'قسم الجباية والإيرادات', 'both', 'الدائرة القانونية، تكنولوجيا المعلومات', 'البنوك المحلية وشركات الدفع الإلكتروني', true, true, true, true, 'high', 45000, 'تمويل ذاتي', 'approved', 'معتمد مع متابعة ربع سنوية.'],
            ['D04', '1.2.2', 'نشر الموازنة المقروءة للمواطن وتقارير الإفصاح المالي الربع سنوية', 'عدد التقارير المنشورة', '4 تقارير', 'منشورات الموقع الرسمي وصفحة البلدية + محاضر اللقاءات المجتمعية', 'قسم الموازنة والحسابات', 'internal', 'العلاقات العامة والإعلام', '', true, true, true, true, 'medium', 14000, 'تمويل ذاتي', 'rejected', 'يرجى توضيح آلية التغذية الراجعة من المواطنين وتقليل تكلفة الطباعة لصالح النشر الرقمي.'],
            ['D05', '3.1.1', 'توريد 120 حاوية نفايات معدنية وتحديث مسارات آليات الجمع', 'عدد الحاويات الموزعة وتغطية المسارات', '120 حاوية', 'سندات التوريد + خرائط التوزيع + تقارير المتابعة الميدانية', 'قسم النظافة والنفايات الصلبة', 'both', 'قسم الحركة والصيانة', 'مجلس الخدمات المشترك', true, true, false, false, 'critical', 148000, 'منح ومشاريع', 'approved', 'معتمد للتنفيذ الفوري في الربع الأول والثاني.'],
            ['D05', '3.1.2', 'تنفيذ حملة تشجير الميادين والمدارس وبرامج التوعية البيئية', 'عدد الأشجار المزروعة وورش التوعية', '1500 شجرة', 'تقارير الزراعة الميدانية + صور موثقة + قوائم المدارس المشاركة', 'قسم الحدائق والتوعية البيئية', 'external', '', 'مديرية التربية والتعليم واللجان الشعبية', true, false, false, true, 'medium', 36000, 'تمويل ذاتي', 'approved', 'معتمد.'],
        ];

        $acts = [];
        foreach ($samples as $s) {
            [$dCode, $pCode, $title, $kpi, $target, $verif, $resp, $supType, $supInt, $supExt, $q1, $q2, $q3, $q4, $prio, $cost, $funding, $status, $note] = $s;
            [$mo, $so, $pr] = $progMap[$pCode];
            [$code, $seq] = AuditService::generateActivityCode($pr->id, $y2027->id);

            $act = Activity::create([
                'operational_plan_id' => $plans[$dCode]->id,
                'planning_year_id' => $y2027->id,
                'department_id' => $departments[$dCode]->id,
                'main_objective_id' => $mo->id,
                'sub_objective_id' => $so->id,
                'program_id' => $pr->id,
                'activity_code' => $code,
                'seq_number' => $seq,
                'title' => $title,
                'kpi_name' => $kpi,
                'kpi_target' => $target,
                'kpi_target_numeric' => AuditService::parseNumeric($target),
                'verification_means' => $verif,
                'responsible_entity' => $resp,
                'support_type' => $supType,
                'support_internal' => $supInt,
                'support_external' => $supExt,
                'q1' => $q1, 'q2' => $q2, 'q3' => $q3, 'q4' => $q4,
                'priority' => $prio,
                'estimated_cost' => $cost,
                'funding_source' => $funding,
                'status' => $status,
                'review_note' => $note,
                'reviewed_by_id' => in_array($status, ['approved', 'rejected'], true) ? $admin->id : null,
                'reviewed_at' => in_array($status, ['approved', 'rejected'], true) ? now() : null,
                'current_version' => 1,
                'created_by_id' => $deptUsers[$dCode]->id,
            ]);
            AuditService::saveActivityVersion($act, 'إنشاء النشاط لأول مرة', $deptUsers[$dCode]);
            $acts[] = $act;
        }

        // Create version 2 on Engineering road project so Version Comparison works out of the box!
        $acts[4]->update([
            'estimated_cost' => 355000,
            'kpi_target' => '5.0 كم',
            'kpi_target_numeric' => 5.0,
            'current_version' => 2,
        ]);
        AuditService::saveActivityVersion($acts[4], 'تحديث نطاق الطريق والتكلفة التقديرية بعد المسح الميداني', $deptUsers['D03']);

        foreach ($plans as $plan) {
            AuditService::syncPlanStatus($plan->id);
        }

        // 7. Monthly Followups
        $followups = [
            [$acts[0], 1, 25, 25, 'مسودة لائحة واحدة', 3500, 'in_progress', 'تم تشكيل اللجنة القانونية وحصر اللوائح القديمة.', ''],
            [$acts[0], 2, 55, 60, 'لائحتان قيد العرض', 4200, 'in_progress', 'عرض المسودة الأولى على المجلس البلدي.', ''],
            [$acts[3], 1, 20, 22, '3 خدمات', 14000, 'in_progress', 'تحليل متطلبات الخدمات الإلكترونية وتصميم الواجهات.', ''],
            [$acts[3], 2, 45, 40, '5 خدمات', 18500, 'delayed', 'تطوير واجهة الطلبات الإلكترونية.', 'تأخر جزئي في الربط مع خادم الأرشفة المركزي.'],
            [$acts[4], 1, 25, 30, '1.2 كم', 85000, 'in_progress', 'إنجاز أعمال البنية التحتية والبردورات للجزء الأول.', ''],
            [$acts[4], 2, 55, 52, '2.4 كم', 92000, 'in_progress', 'توريد البيسكورس وتجهيز طبقة الأسفلت.', 'ارتفاع طفيف في أسعار المواد الأولية.'],
            [$acts[6], 1, 25, 28, '7% نمو', 11000, 'in_progress', 'إطلاق الحملة الإعلامية وتفعيل نافذة التسوية.', ''],
            [$acts[8], 1, 50, 55, '65 حاوية', 78000, 'in_progress', 'توريد الدفعة الأولى وتوزيعها على الأحياء.', ''],
            [$acts[8], 2, 100, 100, '120 حاوية', 69000, 'completed', 'استكمال التوريد والتوزيع بالكامل وتحديث المسارات.', ''],
            [$acts[9], 1, 40, 45, '700 شجرة', 15000, 'in_progress', 'زراعة 700 شجرة في 6 مدارس وميدانين رئيسيين.', ''],
        ];
        foreach ($followups as [$act, $mo, $pl, $ac, $val, $spent, $st, $ach, $obs]) {
            MonthlyFollowup::create([
                'activity_id' => $act->id,
                'planning_year_id' => $y2027->id,
                'month' => $mo,
                'planned_percent' => $pl,
                'actual_percent' => $ac,
                'achieved_value' => $val,
                'actual_cost' => $spent,
                'execution_status' => $st,
                'achievements_note' => $ach,
                'obstacles_note' => $obs,
                'next_steps' => 'متابعة التنفيذ وفق الخطة المعتمدة.',
                'updated_by_id' => $act->created_by_id,
            ]);
        }

        // 8. Risks & Obstacles
        $risks = [
            ['D03', $acts[4]->id, 'ارتفاع أسعار مواد البناء والأسفلت', 'تذبذب أسعار السوق قد يؤدي إلى فجوة تمويلية في بند التعبيد.', 'financial', 4, 4, 'تضمين بند فروقات أسعار ضمن العطاء والبحث عن منحة تكميلية.', 'م. خالد النباهين', 'mitigating'],
            ['D02', $acts[3]->id, 'تأخر الربط التقني مع قواعد البيانات القديمة', 'اختلاف ترميز السجلات الورقية الممسوحة سابقاً.', 'technical', 3, 4, 'تخصيص فريق تدقيق وإدخال بيانات مؤقت خلال الربع الثاني.', 'أ. سارة أبو زايد', 'open'],
            ['D05', $acts[8]->id, 'أعطال متكررة في آليات جمع النفايات', 'تقادم بعض الشاحنات مما يؤثر على انتظام مسارات الجمع.', 'technical', 4, 5, 'إبرام عقد صيانة وقائية عاجل وتأجير آلية بديلة وقت الذروة.', 'م. هبة حمدان', 'escalated'],
            ['D04', $acts[6]->id, 'ضعف إقبال بعض المكلفين على الدفع الإلكتروني', 'تفضيل شريحة من المواطنين للدفع النقدي المباشر.', 'external', 3, 3, 'حملة توعية وتقديم حوافز خصم فوري للسداد الإلكتروني.', 'أ. أحمد درويش', 'mitigating'],
        ];
        foreach ($risks as [$dCode, $actId, $title, $desc, $cat, $lik, $imp, $mit, $owner, $st]) {
            [$score, $sev] = AuditService::calcRiskSeverity($lik, $imp);
            RiskObstacle::create([
                'planning_year_id' => $y2027->id,
                'department_id' => $departments[$dCode]->id,
                'activity_id' => $actId,
                'title' => $title,
                'description' => $desc,
                'category' => $cat,
                'likelihood' => $lik,
                'impact' => $imp,
                'risk_score' => $score,
                'severity' => $sev,
                'mitigation_plan' => $mit,
                'owner_name' => $owner,
                'status' => $st,
                'created_by_id' => $deptUsers[$dCode]->id,
            ]);
        }

        // 9. Comments, Messages & Notifications
        ActivityComment::create([
            'activity_id' => $acts[7]->id,
            'user_id' => $admin->id,
            'body' => 'يرجى مراجعة بند تكلفة الطباعة والاعتماد على النشر الرقمي التفاعلي قبل إعادة الإرسال للاعتماد.',
        ]);

        InternalMessage::create([
            'sender_id' => $admin->id,
            'recipient_department_id' => $departments['D02']->id,
            'recipient_user_id' => $deptUsers['D02']->id,
            'subject' => 'توجيه حول استكمال إدخال أنشطة خطة 2027',
            'body' => 'السلام عليكم، يرجى مراجعة أنشطة الشؤون الإدارية واستكمال مؤشرات الأداء ووسائل التحقق قبل نهاية الشهر الجاري.',
        ]);

        InternalMessage::create([
            'sender_id' => $deptUsers['D03']->id,
            'recipient_user_id' => $admin->id,
            'subject' => 'طلب اعتماد مشروع عدادات المياه الذكية',
            'body' => 'تم إدراج نشاط تركيب عدادات المياه الذكية في المنطقة الغربية وإرساله للاعتماد مع كامل المبررات الفنية والمالية.',
        ]);

        SystemNotification::create([
            'user_id' => $admin->id,
            'title' => 'أنشطة بانتظار المراجعة والاعتماد',
            'message' => 'يوجد نشاطان قيد المراجعة من الشؤون الإدارية والهندسة والمشاريع.',
            'category' => 'submission',
            'link_url' => '/activities?status=pending',
        ]);

        SystemNotification::create([
            'user_id' => $deptUsers['D04']->id,
            'title' => 'تم رفض نشاط مع بيان السبب',
            'message' => 'تم رفض نشاط «نشر الموازنة المقروءة للمواطن» — يرجى الاطلاع على ملاحظة المشرف العام والتعديل.',
            'category' => 'rejection',
            'link_url' => '/activities/' . $acts[7]->id,
        ]);

        AuditService::log('system_init', 'system', null, 'تهيئة نظام الخطة التشغيلية لبلدية النصيرات', ['years' => [2026, 2027]], $admin);
    }
}
