@extends('layouts.app')
@section('title', 'لوحة التحكم ومتابعة الخطط')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">لوحة متابعة الخطط التشغيلية — {{ $year->year }}</h2>
        <p class="page-subtitle">{{ $year->title }} | نظرة شاملة على الأنشطة، الاعتمادات، الموازنات ومؤشرات الأداء</p>
    </div>
    <div class="actions-row">
        <a href="{{ route('activities.create') }}" class="btn btn-success">➕ إدخال نشاط جديد</a>
        <a href="{{ route('reports.excel', 'plan') }}" class="btn btn-primary">📥 تصدير الخطة (Excel)</a>
        <a href="{{ route('reports.pdf', 'plan') }}" class="btn btn-outline">📄 تصدير PDF</a>
    </div>
</div>

<!-- KPI Cards Strip -->
<div class="grid-5" style="margin-bottom:20px;">
    <div class="kpi-card">
        <div class="kpi-label">إجمالي الخطط / الأنشطة</div>
        <div class="kpi-value">{{ $kpis['activities_total'] }}</div>
        <div class="kpi-sub">{{ $kpis['plans_total'] }} خطط دوائر ({{ $kpis['plans_approved'] }} معتمدة)</div>
    </div>
    <div class="kpi-card kpi-emerald">
        <div class="kpi-label">الأنشطة المعتمدة</div>
        <div class="kpi-value" style="color:#059669;">{{ $kpis['activities_approved'] }}</div>
        <div class="kpi-sub">نسبة الاعتماد: {{ $kpis['activities_total'] ? round(($kpis['activities_approved'] / $kpis['activities_total']) * 100, 1) : 0 }}%</div>
    </div>
    <div class="kpi-card kpi-amber">
        <div class="kpi-label">قيد المراجعة / مرفوضة</div>
        <div class="kpi-value" style="color:#d97706;">{{ $kpis['activities_pending'] }} <span style="font-size:16px;color:#dc2626;">/ {{ $kpis['activities_rejected'] }}</span></div>
        <div class="kpi-sub">مسودات: {{ $kpis['activities_draft'] }} نشاط</div>
    </div>
    <div class="kpi-card kpi-sky">
        <div class="kpi-label">متوسط الإنجاز الفعلي</div>
        <div class="kpi-value" style="color:#0284c7;">{{ $kpis['avg_progress'] }}%</div>
        <div class="progress-track" style="margin-top:8px;">
            <div class="progress-fill" style="width:{{ min(100, $kpis['avg_progress']) }}%;"></div>
        </div>
    </div>
    <div class="kpi-card kpi-purple">
        <div class="kpi-label">الموازنة المخططة / الصرف</div>
        <div class="kpi-value" style="font-size:20px;color:#0f3644;">{{ number_format($kpis['total_planned_cost'], 0) }} <span style="font-size:12px;">شيكل</span></div>
        <div class="kpi-sub">الصرف الفعلي: {{ number_format($kpis['total_actual_spent'], 0) }} | مخاطر مفتوحة: {{ $kpis['open_risks'] }}</div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">توزيع الأنشطة حسب الحالة</h3>
            <a href="{{ route('activities.index') }}" class="btn btn-outline btn-sm">إحصائيات الأنشطة والبحث</a>
        </div>
        <div class="card-body">
            <div class="chart-box"><canvas id="statusChart"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">توزيع الأنشطة على الأرباع السنوية (ر1 - ر4)</h3>
            <span class="badge badge-info">الخطة الزمنية {{ $year->year }}</span>
        </div>
        <div class="card-body">
            <div class="chart-box"><canvas id="quartersChart"></canvas></div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">الموازنة المعتمدة مقابل المخطط والصرف الفعلي للدوائر</h3>
            <a href="{{ route('budgets.index') }}" class="btn btn-outline btn-sm">إدارة الموازنات</a>
        </div>
        <div class="card-body">
            <div class="chart-box"><canvas id="budgetChart"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">منحنى المتابعة الشهرية (المخطط مقابل الإنجاز الفعلي %)</h3>
            <a href="{{ route('followups.index') }}" class="btn btn-outline btn-sm">المتابعة الشهرية</a>
        </div>
        <div class="card-body">
            <div class="chart-box"><canvas id="monthlyChart"></canvas></div>
        </div>
    </div>
</div>

<!-- Operational Plans Table (Inspired by V3 Screenshot) -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">الخطط التشغيلية للدوائر — عام {{ $year->year }}</h3>
        <div class="actions-row">
            <a href="{{ route('evaluation.index') }}" class="btn btn-outline btn-sm">🏆 تقييم أداء الدوائر</a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>السنة</th>
                <th>الدائرة</th>
                <th class="text-center">عدد الأنشطة</th>
                <th class="text-center">معتمد</th>
                <th class="text-center">قيد المراجعة</th>
                <th class="text-center">مرفوض</th>
                <th class="text-center">حالة الخطة</th>
                <th class="text-center">الإجراءات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($plans as $plan)
                <tr>
                    <td class="fw-bold">{{ $plan->planningYear?->year }}</td>
                    <td class="fw-bold">{{ $plan->department?->name }}</td>
                    <td class="text-center">{{ $plan->activities_count }}</td>
                    <td class="text-center"><span class="badge badge-success">{{ $plan->approved_count }}</span></td>
                    <td class="text-center"><span class="badge badge-warning">{{ $plan->pending_count }}</span></td>
                    <td class="text-center"><span class="badge badge-danger">{{ $plan->rejected_count }}</span></td>
                    <td class="text-center"><span class="badge {{ $plan->statusBadgeClass() }}">{{ $plan->statusLabel() }}</span></td>
                    <td class="text-center">
                        <a href="{{ route('activities.index', ['department_id' => $plan->department_id]) }}" class="btn btn-primary btn-sm">فتح الخطة</a>
                        <a href="{{ route('reports.excel', ['type' => 'plan', 'department_id' => $plan->department_id]) }}" class="btn btn-outline btn-sm">Excel</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">لا توجد خطط تشغيلية لهذه السنة بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Bottom Row: Create Plan + Top Risks -->
<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title">إنشاء أو فتح خطة تشغيلية جديدة</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('plans.create') }}">
                @csrf
                <input type="hidden" name="planning_year_id" value="{{ $year->id }}">
                <div class="form-group">
                    <label class="form-label">السنة التخطيطية</label>
                    <input type="text" class="form-control" value="{{ $year->year }} — {{ $year->title }}" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">الدائرة</label>
                    @if(auth()->user()->isAdmin())
                        <select name="department_id" class="form-select" required>
                            <option value="">-- اختر الدائرة --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="department_id" value="{{ auth()->user()->department_id }}">
                        <input type="text" class="form-control" value="{{ auth()->user()->department?->name }}" disabled>
                    @endif
                </div>
                <div class="form-group">
                    <label class="form-label">ملاحظات افتتاحية (اختياري)</label>
                    <input type="text" name="notes" class="form-control" placeholder="أي توجيهات أو ملاحظات عامة للخطة">
                </div>
                <button type="submit" class="btn btn-primary" @disabled(!$entryOpen && !auth()->user()->isAdmin())>
                    إنشاء / فتح الخطة التشغيلية
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">أبرز المخاطر والمعوقات النشطة</h3>
            <a href="{{ route('risks.index') }}" class="btn btn-outline btn-sm">سجل المخاطر الكامل</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>الخطر / المعوق</th>
                    <th>الدائرة</th>
                    <th class="text-center">المستوى</th>
                    <th class="text-center">الحالة</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentRisks as $r)
                    <tr>
                        <td class="fw-bold">{{ $r->title }}</td>
                        <td>{{ $r->department?->name }}</td>
                        <td class="text-center"><span class="badge {{ $r->severityBadgeClass() }}">{{ $r->severityLabel() }} ({{ $r->risk_score }})</span></td>
                        <td class="text-center"><span class="badge badge-secondary">{{ $r->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted" style="padding:20px;">لا توجد مخاطر مسجلة لهذه السنة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const data = @json($chartData);
    Chart.defaults.font.family = "'Cairo', 'Tahoma', Arial, sans-serif";

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['معتمد', 'قيد المراجعة', 'مرفوض', 'مسودة'],
            datasets: [{
                data: data.status,
                backgroundColor: ['#059669', '#f59e0b', '#dc2626', '#94a3b8'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('quartersChart'), {
        type: 'bar',
        data: {
            labels: ['الربع الأول (ر1)', 'الربع الثاني (ر2)', 'الربع الثالث (ر3)', 'الربع الرابع (ر4)'],
            datasets: [{
                label: 'عدد الأنشطة المجدولة',
                data: data.quarters,
                backgroundColor: ['#0f4c5c', '#0284c7', '#059669', '#7c3aed'],
                borderRadius: 8
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    new Chart(document.getElementById('budgetChart'), {
        type: 'bar',
        data: {
            labels: data.deptLabels,
            datasets: [
                { label: 'الموازنة المعتمدة', data: data.deptAllocated, backgroundColor: '#0f4c5c', borderRadius: 5 },
                { label: 'التكلفة المخططة', data: data.deptPlanned, backgroundColor: '#0284c7', borderRadius: 5 },
                { label: 'الصرف الفعلي', data: data.deptSpent, backgroundColor: '#10b981', borderRadius: 5 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });

    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: {
            labels: ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'],
            datasets: [
                { label: 'المخطط %', data: data.monthlyPlanned, borderColor: '#0284c7', backgroundColor: 'rgba(2,132,199,0.1)', tension: 0.35, fill: true },
                { label: 'الفعلي %', data: data.monthlyActual, borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.12)', tension: 0.35, fill: true }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 100 } } }
    });
});
</script>
@endpush
