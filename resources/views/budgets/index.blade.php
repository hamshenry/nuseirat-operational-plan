@extends('layouts.app')
@section('title', 'إدارة التكاليف والموازنات')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">💰 إدارة التكاليف والموازنات — عام {{ $year->year }}</h2>
        <p class="page-subtitle">تخصيص سقوف الموازنات حسب مصادر التمويل، ومراقبة التكلفة المخططة مقابل الصرف الفعلي والانحراف المالي</p>
    </div>
    <div class="actions-row">
        @if(auth()->user()->isAdmin())
            <button type="button" class="btn btn-success" onclick="openBudgetModalNew()">➕ تخصيص / تعديل موازنة دائرة</button>
        @endif
        <a href="{{ route('reports.excel', 'budgets') }}" class="btn btn-primary">📥 تصدير Excel</a>
        <a href="{{ route('reports.pdf', 'budgets') }}" class="btn btn-outline">📄 تصدير PDF</a>
    </div>
</div>

<div class="grid-4" style="margin-bottom:18px;">
    <div class="kpi-card">
        <div class="kpi-label">إجمالي الموازنة المعتمدة</div>
        <div class="kpi-value" style="font-size:23px;">{{ number_format($totals['allocated'], 0) }} <span style="font-size:12px;">شيكل</span></div>
    </div>
    <div class="kpi-card kpi-sky">
        <div class="kpi-label">التكلفة المخططة للأنشطة</div>
        <div class="kpi-value" style="font-size:23px;color:#0284c7;">{{ number_format($totals['planned'], 0) }} <span style="font-size:12px;">شيكل</span></div>
    </div>
    <div class="kpi-card kpi-emerald">
        <div class="kpi-label">إجمالي الصرف الفعلي</div>
        <div class="kpi-value" style="font-size:23px;color:#059669;">{{ number_format($totals['actual'], 0) }} <span style="font-size:12px;">شيكل</span></div>
    </div>
    <div class="kpi-card {{ $totals['remaining'] < 0 ? 'kpi-red' : 'kpi-purple' }}">
        <div class="kpi-label">المتبقي من الموازنة</div>
        <div class="kpi-value" style="font-size:23px;color:{{ $totals['remaining'] < 0 ? '#dc2626' : '#0f3644' }};">{{ number_format($totals['remaining'], 0) }} <span style="font-size:12px;">شيكل</span></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">مقارنة الموازنة المعتمدة والمخطط والفعلي لكل دائرة</h3></div>
    <div class="card-body"><div class="chart-box"><canvas id="budgetsPageChart"></canvas></div></div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">تفصيل موازنات الدوائر ومصادر التمويل</h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>الدائرة</th>
                <th class="text-center">الموازنة المعتمدة</th>
                <th class="text-center">ذاتي</th>
                <th class="text-center">منح ومشاريع</th>
                <th class="text-center">خارجي</th>
                <th class="text-center">المخطط للأنشطة</th>
                <th class="text-center">الصرف الفعلي</th>
                <th class="text-center">المتبقي</th>
                <th class="text-center">الحالة</th>
                @if(auth()->user()->isAdmin())<th class="text-center">إجراء</th>@endif
            </tr>
            </thead>
            <tbody>
            @foreach($evaluations as $e)
                @php
                    $al = $allocations->get($e['department']->id);
                    $rem = $e['allocated_budget'] - $e['actual_spent'];
                    $overPlanned = $e['allocated_budget'] > 0 && $e['planned_cost'] > $e['allocated_budget'];
                    $bData = [
                        'department_id' => $e['department']->id,
                        'self_funded' => (float) ($al?->self_funded ?? 0),
                        'grants_funded' => (float) ($al?->grants_funded ?? 0),
                        'external_funded' => (float) ($al?->external_funded ?? 0),
                        'notes' => $al?->notes ?? '',
                    ];
                @endphp
                <tr>
                    <td class="fw-bold">{{ $e['department']->name }}</td>
                    <td class="text-center fw-bold">{{ number_format($e['allocated_budget'], 2) }}</td>
                    <td class="text-center">{{ number_format((float)($al?->self_funded ?? 0), 2) }}</td>
                    <td class="text-center">{{ number_format((float)($al?->grants_funded ?? 0), 2) }}</td>
                    <td class="text-center">{{ number_format((float)($al?->external_funded ?? 0), 2) }}</td>
                    <td class="text-center" style="color:#0284c7;font-weight:700;">{{ number_format($e['planned_cost'], 2) }}</td>
                    <td class="text-center" style="color:#059669;font-weight:700;">{{ number_format($e['actual_spent'], 2) }}</td>
                    <td class="text-center fw-bold" style="color:{{ $rem < 0 ? '#dc2626' : '#0f3644' }};">{{ number_format($rem, 2) }}</td>
                    <td class="text-center">
                        @if($overPlanned)
                            <span class="badge badge-danger">⚠️ تجاوز السقف</span>
                        @elseif($e['allocated_budget'] == 0)
                            <span class="badge badge-secondary">غير مخصص</span>
                        @else
                            <span class="badge badge-success">ضمن السقف</span>
                        @endif
                    </td>
                    @if(auth()->user()->isAdmin())
                        <td class="text-center" style="white-space:nowrap;">
                            <button type="button" class="btn btn-info btn-sm"
                                    data-json="{{ json_encode($bData, JSON_UNESCAPED_UNICODE) }}"
                                    onclick="openEditBudget(this)">تعديل</button>
                            @if($al)
                                <form method="POST" action="{{ route('budgets.destroy', $al) }}" style="display:inline;" onsubmit="return confirm('نقل مخصص الموازنة إلى سلة المحذوفات؟');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                                </form>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">توزيع التكاليف حسب الأهداف الرئيسية</h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>الهدف الرئيسي</th>
                <th class="text-center">عدد الأنشطة</th>
                <th class="text-center">التكلفة المخططة</th>
                <th class="text-center">الصرف الفعلي</th>
            </tr>
            </thead>
            <tbody>
            @foreach($objectivesCost as $oc)
                <tr>
                    <td class="fw-bold">{{ $oc['code'] }} — {{ $oc['title'] }}</td>
                    <td class="text-center">{{ $oc['count'] }}</td>
                    <td class="text-center fw-bold">{{ number_format($oc['planned'], 2) }} شيكل</td>
                    <td class="text-center" style="color:#059669;font-weight:700;">{{ number_format($oc['actual'], 2) }} شيكل</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if(auth()->user()->isAdmin())
<div class="modal-backdrop" id="budgetModal">
    <div class="modal-box">
        <div class="modal-header"><h3 class="modal-title">تخصيص / تعديل موازنة دائرة ({{ $year->year }})</h3><button type="button" class="modal-close" onclick="closeModal('budgetModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" action="{{ route('budgets.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">الدائرة *</label>
                    <select name="department_id" id="bm_dept" class="form-select" required>
                        @foreach($departments as $d)
                            <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-grid-3">
                    <div class="form-group"><label class="form-label">تمويل ذاتي (شيكل)</label><input type="number" step="0.01" min="0" name="self_funded" id="bm_self" class="form-control" required value="0" oninput="updateBudgetTotal()"></div>
                    <div class="form-group"><label class="form-label">منح ومشاريع (شيكل)</label><input type="number" step="0.01" min="0" name="grants_funded" id="bm_grants" class="form-control" required value="0" oninput="updateBudgetTotal()"></div>
                    <div class="form-group"><label class="form-label">تمويل خارجي (شيكل)</label><input type="number" step="0.01" min="0" name="external_funded" id="bm_ext" class="form-control" required value="0" oninput="updateBudgetTotal()"></div>
                </div>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:10px 14px;border-radius:9px;margin-bottom:12px;font-weight:800;color:#166534;">
                    إجمالي الموازنة المعتمدة: <span id="bm_total">0.00</span> شيكل
                </div>
                <div class="form-group">
                    <label class="form-label">ملاحظات مالية</label>
                    <textarea name="notes" id="bm_notes" class="form-control"></textarea>
                </div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-success">حفظ الموازنة المعتمدة</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('budgetModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
function updateBudgetTotal() {
    const s = parseFloat(document.getElementById('bm_self')?.value || 0) || 0;
    const g = parseFloat(document.getElementById('bm_grants')?.value || 0) || 0;
    const e = parseFloat(document.getElementById('bm_ext')?.value || 0) || 0;
    const el = document.getElementById('bm_total');
    if (el) el.textContent = (s + g + e).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function openBudgetModalNew() {
    document.getElementById('bm_self').value = 0;
    document.getElementById('bm_grants').value = 0;
    document.getElementById('bm_ext').value = 0;
    document.getElementById('bm_notes').value = '';
    updateBudgetTotal();
    openModal('budgetModal');
}
function openEditBudget(btn) {
    const d = parseBtnData(btn);
    if (!d) return;
    document.getElementById('bm_dept').value = d.department_id;
    document.getElementById('bm_self').value = d.self_funded;
    document.getElementById('bm_grants').value = d.grants_funded;
    document.getElementById('bm_ext').value = d.external_funded;
    document.getElementById('bm_notes').value = d.notes || '';
    updateBudgetTotal();
    openModal('budgetModal');
}
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const d = @json($chartData);
    new Chart(document.getElementById('budgetsPageChart'), {
        type: 'bar',
        data: {
            labels: d.labels,
            datasets: [
                { label: 'الموازنة المعتمدة', data: d.allocated, backgroundColor: '#0f4c5c', borderRadius: 5 },
                { label: 'التكلفة المخططة', data: d.planned, backgroundColor: '#0284c7', borderRadius: 5 },
                { label: 'الصرف الفعلي', data: d.actual, backgroundColor: '#059669', borderRadius: 5 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
});
</script>
@endpush
