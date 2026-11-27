<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: xbriyaz, dejavusans, sans-serif;
            direction: rtl;
            font-size: 10pt;
            color: #0f172a;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f3644;
            margin-bottom: 12px;
            padding-bottom: 8px;
        }
        .header-table td {
            border: none;
            vertical-align: middle;
        }
        .title-main { font-size: 14pt; font-weight: bold; color: #0f3644; }
        .title-sub { font-size: 11.5pt; font-weight: bold; color: #047857; margin-top: 3px; }
        .meta { font-size: 8.5pt; color: #475569; margin-top: 2px; }
        table.rep-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.rep-table th {
            background: #0f3644;
            color: #ffffff;
            padding: 6px 5px;
            border: 1px solid #cbd5e1;
            font-size: 9pt;
            text-align: center;
        }
        table.rep-table td {
            padding: 5px 5px;
            border: 1px solid #cbd5e1;
            font-size: 8.8pt;
            vertical-align: middle;
        }
        table.rep-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .center { text-align: center; }
        .total-row td {
            background: #e2e8f0 !important;
            font-weight: bold;
            color: #0f3644;
        }
        .q-ok { background: #dcfce7 !important; color: #166534; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
<table class="header-table">
    <tr>
        <td style="width:70px;text-align:right;">
            @if(!empty($logoBase64))
                <img src="{{ $logoBase64 }}" style="width:60px;height:60px;">
            @endif
        </td>
        <td style="text-align:center;">
            <div class="title-main">دولة فلسطين — وزارة الحكم المحلي — بلدية النصيرات</div>
            <div class="title-sub">{{ $title }} {{ $department ? ('— ' . $department->name) : '— جميع الدوائر' }}</div>
            <div class="meta">تاريخ الإصدار: {{ $generatedAt }} | نظام إدارة الخطة التشغيلية</div>
        </td>
        <td style="width:70px;"></td>
    </tr>
</table>

@if($type === 'plan')
    <table class="rep-table">
        <thead>
        <tr>
            <th>رقم النشاط</th>
            <th>الهدف الرئيسي</th>
            <th>الهدف الفرعي</th>
            <th>البرنامج</th>
            <th>النشاط</th>
            <th>مؤشر الأداء</th>
            <th>القيمة</th>
            <th>وسائل التحقق</th>
            <th>المسئولة</th>
            <th>مساندة داخلي</th>
            <th>مساندة خارجي</th>
            <th>ر1</th><th>ر2</th><th>ر3</th><th>ر4</th>
            <th>الأهمية</th>
            <th>التكلفة</th>
            <th>الدائرة</th>
            <th>الحالة</th>
        </tr>
        </thead>
        <tbody>
        @foreach($activities as $act)
            <tr>
                <td class="center">{{ $act->activity_code }}</td>
                <td>{{ $act->mainObjective?->title }}</td>
                <td>{{ $act->subObjective?->title }}</td>
                <td>{{ $act->program?->title }}</td>
                <td>{{ $act->title }}</td>
                <td>{{ $act->kpi_name }}</td>
                <td class="center">{{ $act->kpi_target }}</td>
                <td>{{ $act->verification_means }}</td>
                <td>{{ $act->responsible_entity }}</td>
                <td>{{ $act->support_internal ?: '—' }}</td>
                <td>{{ $act->support_external ?: '—' }}</td>
                <td class="{{ $act->q1 ? 'q-ok' : 'center' }}">{{ $act->q1 ? '✓' : '' }}</td>
                <td class="{{ $act->q2 ? 'q-ok' : 'center' }}">{{ $act->q2 ? '✓' : '' }}</td>
                <td class="{{ $act->q3 ? 'q-ok' : 'center' }}">{{ $act->q3 ? '✓' : '' }}</td>
                <td class="{{ $act->q4 ? 'q-ok' : 'center' }}">{{ $act->q4 ? '✓' : '' }}</td>
                <td class="center">{{ $act->priorityLabel() }}</td>
                <td class="center">{{ number_format((float)$act->estimated_cost, 2) }}</td>
                <td>{{ $act->department?->name }}</td>
                <td class="center">{{ $act->statusLabel() }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="16" class="center">إجمالي عدد الأنشطة: {{ $activities->count() }} — إجمالي التكلفة التقديرية</td>
            <td class="center">{{ number_format((float)$activities->sum('estimated_cost'), 2) }}</td>
            <td colspan="2" class="center">شيكل</td>
        </tr>
        </tbody>
    </table>
@elseif($type === 'evaluation')
    <table class="rep-table">
        <thead>
        <tr>
            <th>الترتيب</th><th>الدائرة</th><th>إجمالي الأنشطة</th><th>المعتمدة</th>
            <th>متوسط الإنجاز %</th><th>الاعتماد %</th><th>الالتزام %</th><th>كفاءة الموازنة %</th>
            <th>المتابعة والتوثيق %</th><th>الدرجة النهائية</th><th>التقدير</th>
        </tr>
        </thead>
        <tbody>
        @foreach($evaluations as $e)
            <tr>
                <td class="center">{{ $e['rank'] }}</td>
                <td>{{ $e['department']->name }}</td>
                <td class="center">{{ $e['total_activities'] }}</td>
                <td class="center">{{ $e['approved'] }}</td>
                <td class="center">{{ $e['avg_progress'] }}%</td>
                <td class="center">{{ $e['approval_rate'] }}%</td>
                <td class="center">{{ $e['timeliness_rate'] }}%</td>
                <td class="center">{{ $e['budget_score'] }}%</td>
                <td class="center">{{ $e['doc_rate'] }}%</td>
                <td class="center"><strong>{{ $e['score'] }} / 100</strong></td>
                <td class="center">{{ $e['grade'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@elseif($type === 'budgets')
    <table class="rep-table">
        <thead>
        <tr>
            <th>الدائرة</th><th>الموازنة المعتمدة</th><th>تمويل ذاتي</th><th>منح ومشاريع</th>
            <th>تمويل خارجي</th><th>المخطط للأنشطة</th><th>الصرف الفعلي</th><th>المتبقي</th>
        </tr>
        </thead>
        <tbody>
        @foreach($evaluations as $e)
            @php $al = $allocations->get($e['department']->id); @endphp
            <tr>
                <td>{{ $e['department']->name }}</td>
                <td class="center">{{ number_format($e['allocated_budget'], 2) }}</td>
                <td class="center">{{ number_format((float)($al?->self_funded ?? 0), 2) }}</td>
                <td class="center">{{ number_format((float)($al?->grants_funded ?? 0), 2) }}</td>
                <td class="center">{{ number_format((float)($al?->external_funded ?? 0), 2) }}</td>
                <td class="center">{{ number_format($e['planned_cost'], 2) }}</td>
                <td class="center">{{ number_format($e['actual_spent'], 2) }}</td>
                <td class="center">{{ number_format($e['allocated_budget'] - $e['actual_spent'], 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@elseif($type === 'monthly')
    <table class="rep-table">
        <thead>
        <tr>
            <th>الشهر</th><th>الدائرة</th><th>رقم النشاط</th><th>النشاط</th>
            <th>المخطط %</th><th>الفعلي %</th><th>المتحقق</th><th>الصرف الفعلي</th><th>الحالة</th><th>الإنجازات والمعوقات</th>
        </tr>
        </thead>
        <tbody>
        @foreach($followups as $f)
            <tr>
                <td class="center">{{ \App\Models\MonthlyFollowup::monthName($f->month) }}</td>
                <td>{{ $f->activity?->department?->name }}</td>
                <td class="center">{{ $f->activity?->activity_code }}</td>
                <td>{{ $f->activity?->title }}</td>
                <td class="center">{{ $f->planned_percent }}%</td>
                <td class="center">{{ $f->actual_percent }}%</td>
                <td class="center">{{ $f->achieved_value ?: '—' }}</td>
                <td class="center">{{ number_format((float)$f->actual_cost, 2) }}</td>
                <td class="center">{{ $f->executionStatusLabel() }}</td>
                <td>{{ $f->achievements_note }} @if($f->obstacles_note) | معوقات: {{ $f->obstacles_note }} @endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <table class="rep-table">
        <thead>
        <tr>
            <th>الدائرة</th><th>النشاط المرتبط</th><th>عنوان الخطر / المعوق</th><th>التصنيف</th>
            <th>الاحتمالية</th><th>الأثر</th><th>الدرجة</th><th>المستوى</th><th>خطة المعالجة</th><th>الحالة</th>
        </tr>
        </thead>
        <tbody>
        @foreach($risks as $r)
            <tr>
                <td>{{ $r->department?->name }}</td>
                <td>{{ $r->activity ? $r->activity->activity_code : 'عام' }}</td>
                <td>{{ $r->title }}</td>
                <td class="center">{{ $r->categoryLabel() }}</td>
                <td class="center">{{ $r->likelihood }}</td>
                <td class="center">{{ $r->impact }}</td>
                <td class="center">{{ $r->risk_score }}/25</td>
                <td class="center">{{ $r->severityLabel() }}</td>
                <td>{{ $r->mitigation_plan ?: '—' }}</td>
                <td class="center">{{ $r->statusLabel() }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
