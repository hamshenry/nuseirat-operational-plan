@extends('layouts.app')
@section('title', 'تقييم أداء الدوائر')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">🏆 تقييم أداء الدوائر البلدية — عام {{ $year->year }}</h2>
        <p class="page-subtitle">معادلة التقييم الموزونة: 30% الإنجاز الفعلي + 25% الاعتماد والجودة + 20% الالتزام الزمني + 15% كفاءة الموازنة + 10% المتابعة والتوثيق</p>
    </div>
    <div class="actions-row">
        <a href="{{ route('reports.excel', 'evaluation') }}" class="btn btn-primary">📥 تصدير Excel</a>
        <a href="{{ route('reports.pdf', 'evaluation') }}" class="btn btn-outline">📄 تصدير PDF</a>
    </div>
</div>

<div class="grid-3" style="margin-bottom:18px;">
    <div class="kpi-card kpi-emerald">
        <div class="kpi-label">متوسط الأداء العام للبلدية</div>
        <div class="kpi-value" style="color:#059669;">{{ $municipalityAvg }} / 100</div>
        <div class="kpi-sub">بناءً على تقييم {{ count($evaluations) }} دائرة</div>
    </div>
    @if(count($evaluations) > 0)
        <div class="kpi-card kpi-sky">
            <div class="kpi-label">الدائرة الأعلى أداءً (المركز الأول)</div>
            <div class="kpi-value" style="font-size:20px;color:#0f3644;">🥇 {{ $evaluations[0]['department']->name }}</div>
            <div class="kpi-sub">الدرجة: {{ $evaluations[0]['score'] }} / 100 ({{ $evaluations[0]['grade'] }})</div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-label">إجمالي الأنشطة والأدلة الموثقة</div>
            <div class="kpi-value">{{ collect($evaluations)->sum('total_activities') }}</div>
            <div class="kpi-sub">تقارير متابعة: {{ collect($evaluations)->sum('followups_count') }} | مرفقات: {{ collect($evaluations)->sum('attachments_count') }}</div>
        </div>
    @endif
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">مقارنة بيانية لدرجات تقييم الدوائر</h3></div>
    <div class="card-body">
        <div class="chart-box"><canvas id="evalChart"></canvas></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">جدول الترتيب والتقييم التفصيلي للدوائر</h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th class="text-center">الترتيب</th>
                <th>الدائرة</th>
                <th class="text-center">الأنشطة (معتمد/الكل)</th>
                <th class="text-center">الإنجاز الفعلي (30%)</th>
                <th class="text-center">الاعتماد (25%)</th>
                <th class="text-center">الالتزام (20%)</th>
                <th class="text-center">الموازنة (15%)</th>
                <th class="text-center">التوثيق (10%)</th>
                <th class="text-center">الدرجة النهائية</th>
                <th class="text-center">التقدير</th>
            </tr>
            </thead>
            <tbody>
            @foreach($evaluations as $e)
                <tr>
                    <td class="text-center fw-bold" style="font-size:16px;">
                        @if($e['rank'] === 1) 🥇 @elseif($e['rank'] === 2) 🥈 @elseif($e['rank'] === 3) 🥉 @else #{{ $e['rank'] }} @endif
                    </td>
                    <td class="fw-bold">{{ $e['department']->name }}</td>
                    <td class="text-center">{{ $e['approved'] }} / {{ $e['total_activities'] }}</td>
                    <td class="text-center">{{ $e['avg_progress'] }}%</td>
                    <td class="text-center">{{ $e['approval_rate'] }}%</td>
                    <td class="text-center">{{ $e['timeliness_rate'] }}%</td>
                    <td class="text-center">{{ $e['budget_score'] }}%</td>
                    <td class="text-center">{{ $e['doc_rate'] }}%</td>
                    <td class="text-center">
                        <strong style="font-size:16px;color:var(--primary);">{{ $e['score'] }}</strong>
                        <div class="progress-track" style="margin-top:4px;"><div class="progress-fill" style="width:{{ min(100, $e['score']) }}%;"></div></div>
                    </td>
                    <td class="text-center"><span class="badge {{ $e['grade_class'] }}">{{ $e['grade'] }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const d = @json($chartData);
    new Chart(document.getElementById('evalChart'), {
        type: 'bar',
        data: {
            labels: d.labels,
            datasets: [
                { label: 'الدرجة النهائية /100', data: d.scores, backgroundColor: '#0f4c5c', borderRadius: 6 },
                { label: 'متوسط الإنجاز %', data: d.progress, backgroundColor: '#10b981', borderRadius: 6 },
                { label: 'نسبة الاعتماد %', data: d.approval, backgroundColor: '#0284c7', borderRadius: 6 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 100 } } }
    });
});
</script>
@endpush
