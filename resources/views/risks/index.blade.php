@extends('layouts.app')
@section('title', 'إدارة المخاطر والمعوقات')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">⚠️ إدارة المخاطر والمعوقات — عام {{ $year->year }}</h2>
        <p class="page-subtitle">رصد وتحليل المخاطر التشغيلية والمالية والتقنية وفق مصفوفة (الاحتمالية × الأثر) وخطط المعالجة</p>
    </div>
    <div class="actions-row">
        <button type="button" class="btn btn-success" onclick="openModal('createRiskModal')">➕ تسجيل خطر / معوق</button>
        <a href="{{ route('reports.excel', 'risks') }}" class="btn btn-primary">📥 Excel</a>
        <a href="{{ route('reports.pdf', 'risks') }}" class="btn btn-outline">📄 PDF</a>
    </div>
</div>

<!-- 5x5 Risk Matrix Heatmap -->
<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title">مصفوفة تقييم المخاطر (5 × 5)</h3></div>
        <div class="card-body">
            <table class="risk-matrix">
                <thead>
                <tr>
                    <th>الاحتمالية \ الأثر</th>
                    <th>1 (ضئيل)</th><th>2 (بسيط)</th><th>3 (متوسط)</th><th>4 (كبير)</th><th>5 (كارثي)</th>
                </tr>
                </thead>
                <tbody>
                @for($l = 5; $l >= 1; $l--)
                    <tr>
                        <th>{{ $l }}</th>
                        @for($i = 1; $i <= 5; $i++)
                            @php
                                $sc = $l * $i;
                                $cls = $sc >= 16 ? 'rm-critical' : ($sc >= 10 ? 'rm-high' : ($sc >= 5 ? 'rm-medium' : 'rm-low'));
                                $cnt = $matrix[$l][$i] ?? 0;
                            @endphp
                            <td class="{{ $cls }}">
                                {{ $cnt > 0 ? $cnt . ' خطر' : '—' }}
                                <div style="font-size:10px;opacity:0.7;">({{ $sc }})</div>
                            </td>
                        @endfor
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">ملخص وتصفية المخاطر</h3></div>
        <div class="card-body">
            <div class="grid-2" style="margin-bottom:14px;">
                <div class="kpi-card kpi-red" style="padding:12px;">
                    <div class="kpi-label">مخاطر حرجة ومرتفعة</div>
                    <div class="kpi-value" style="font-size:22px;color:#dc2626;">{{ $risks->whereIn('severity', ['critical', 'high'])->count() }}</div>
                </div>
                <div class="kpi-card kpi-emerald" style="padding:12px;">
                    <div class="kpi-label">تم حلها / قيد المعالجة</div>
                    <div class="kpi-value" style="font-size:22px;color:#059669;">{{ $risks->whereIn('status', ['resolved', 'mitigating'])->count() }}</div>
                </div>
            </div>
            <form method="GET" action="{{ route('risks.index') }}" class="form-grid">
                <div class="form-group">
                    <label class="form-label">مستوى الخطورة</label>
                    <select name="severity" class="form-select">
                        <option value="">الكل</option>
                        <option value="critical" @selected(request('severity')==='critical')>حرج</option>
                        <option value="high" @selected(request('severity')==='high')>مرتفع</option>
                        <option value="medium" @selected(request('severity')==='medium')>متوسط</option>
                        <option value="low" @selected(request('severity')==='low')>منخفض</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">الكل</option>
                        <option value="open" @selected(request('status')==='open')>مفتوح</option>
                        <option value="mitigating" @selected(request('status')==='mitigating')>قيد المعالجة</option>
                        <option value="escalated" @selected(request('status')==='escalated')>مصعّد للإدارة</option>
                        <option value="resolved" @selected(request('status')==='resolved')>تم الحل</option>
                    </select>
                </div>
                <div class="form-group form-full">
                    <button type="submit" class="btn btn-primary btn-sm">تطبيق التصفية</button>
                    <a href="{{ route('risks.index') }}" class="btn btn-outline btn-sm">إعادة ضبط</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Risks Register Table -->
<div class="card">
    <div class="card-header"><h3 class="card-title">سجل المخاطر والمعوقات ({{ $risks->count() }})</h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>الخطر / المعوق</th>
                <th>الدائرة والنشاط</th>
                <th class="text-center">التصنيف</th>
                <th class="text-center">الدرجة والمستوى</th>
                <th>خطة المعالجة والتخفيف</th>
                <th>المسؤول</th>
                <th class="text-center">الحالة</th>
                <th class="text-center">الإجراءات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($risks as $r)
                <tr>
                    <td style="min-width:200px;">
                        <div class="fw-bold">{{ $r->title }}</div>
                        <div class="text-muted" style="font-size:12px;">{{ $r->description }}</div>
                    </td>
                    <td>
                        <div class="fw-bold">{{ $r->department?->name }}</div>
                        @if($r->activity)
                            <a href="{{ route('activities.show', $r->activity) }}" style="font-size:12px;">{{ $r->activity->activity_code }}</a>
                        @else
                            <span class="text-muted" style="font-size:12px;">خطر عام</span>
                        @endif
                    </td>
                    <td class="text-center"><span class="badge badge-secondary">{{ $r->categoryLabel() }}</span></td>
                    <td class="text-center">
                        <span class="badge {{ $r->severityBadgeClass() }}">{{ $r->severityLabel() }} ({{ $r->risk_score }}/25)</span>
                        <div style="font-size:11px;color:var(--text-soft);">احتمالية {{ $r->likelihood }} × أثر {{ $r->impact }}</div>
                    </td>
                    <td style="min-width:210px;font-size:13px;">{{ $r->mitigation_plan ?: '—' }}</td>
                    <td>{{ $r->owner_name ?: '—' }}</td>
                    <td class="text-center"><span class="badge badge-info">{{ $r->statusLabel() }}</span></td>
                    <td class="text-center" style="white-space:nowrap;">
                        <button type="button" class="btn btn-info btn-sm" onclick='openEditRisk(@json($r))'>تعديل</button>
                        <form method="POST" action="{{ route('risks.destroy', $r) }}" style="display:inline;" onsubmit="return confirm('نقل الخطر إلى سلة المحذوفات؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted" style="padding:26px;">لا توجد مخاطر أو معوقات مسجلة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Create Risk Modal -->
<div class="modal-backdrop" id="createRiskModal">
    <div class="modal-box">
        <div class="modal-header"><h3 class="modal-title">تسجيل خطر أو معوق جديد</h3><button class="modal-close" onclick="closeModal('createRiskModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" action="{{ route('risks.store') }}">
                @csrf
                <div class="form-grid">
                    @if(auth()->user()->isAdmin())
                        <div class="form-group">
                            <label class="form-label">الدائرة *</label>
                            <select name="department_id" class="form-select" required>
                                @foreach($departments as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="form-group">
                        <label class="form-label">النشاط المرتبط (اختياري)</label>
                        <select name="activity_id" class="form-select">
                            <option value="">خطر عام على مستوى الدائرة</option>
                            @foreach($activities as $a)
                                <option value="{{ $a->id }}">{{ $a->activity_code }} — {{ \Illuminate\Support\Str::limit($a->title, 45) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">عنوان الخطر / المعوق *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">الوصف والتفاصيل</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">التصنيف *</label>
                        <select name="category" class="form-select">
                            <option value="financial">مالي</option>
                            <option value="technical">فني وتقني</option>
                            <option value="administrative">إداري وتنظيمي</option>
                            <option value="external">خارجي ومجتمعي</option>
                            <option value="legal">قانوني وتشريعي</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الحالة *</label>
                        <select name="status" class="form-select">
                            <option value="open">مفتوح</option>
                            <option value="mitigating">قيد المعالجة</option>
                            <option value="escalated">مصعّد للإدارة</option>
                            <option value="resolved">تم الحل</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاحتمالية (1 إلى 5) *</label>
                        <input type="number" min="1" max="5" name="likelihood" class="form-control" required value="3">
                    </div>
                    <div class="form-group">
                        <label class="form-label">شدة الأثر (1 إلى 5) *</label>
                        <input type="number" min="1" max="5" name="impact" class="form-control" required value="3">
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">خطة المعالجة والتخفيف</label>
                        <textarea name="mitigation_plan" class="form-control"></textarea>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">المسؤول عن المتابعة</label>
                        <input type="text" name="owner_name" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-success">حفظ الخطر في السجل</button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Risk Modal -->
<div class="modal-backdrop" id="editRiskModal">
    <div class="modal-box">
        <div class="modal-header"><h3 class="modal-title">تعديل بيانات الخطر / المعوق</h3><button class="modal-close" onclick="closeModal('editRiskModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" id="editRiskForm">
                @csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group form-full"><label class="form-label">العنوان *</label><input type="text" name="title" id="er_title" class="form-control" required></div>
                    <div class="form-group form-full"><label class="form-label">الوصف</label><textarea name="description" id="er_desc" class="form-control"></textarea></div>
                    <div class="form-group">
                        <label class="form-label">التصنيف</label>
                        <select name="category" id="er_cat" class="form-select">
                            <option value="financial">مالي</option><option value="technical">فني وتقني</option>
                            <option value="administrative">إداري وتنظيمي</option><option value="external">خارجي ومجتمعي</option><option value="legal">قانوني وتشريعي</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الحالة</label>
                        <select name="status" id="er_status" class="form-select">
                            <option value="open">مفتوح</option><option value="mitigating">قيد المعالجة</option>
                            <option value="escalated">مصعّد للإدارة</option><option value="resolved">تم الحل</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">الاحتمالية (1-5)</label><input type="number" min="1" max="5" name="likelihood" id="er_lik" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">الأثر (1-5)</label><input type="number" min="1" max="5" name="impact" id="er_imp" class="form-control" required></div>
                    <div class="form-group form-full"><label class="form-label">خطة المعالجة</label><textarea name="mitigation_plan" id="er_mit" class="form-control"></textarea></div>
                    <div class="form-group form-full"><label class="form-label">المسؤول</label><input type="text" name="owner_name" id="er_owner" class="form-control"></div>
                </div>
                <button type="submit" class="btn btn-primary">حفظ التعديل</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openEditRisk(r) {
    document.getElementById('editRiskForm').action = `/risks/${r.id}`;
    document.getElementById('er_title').value = r.title || '';
    document.getElementById('er_desc').value = r.description || '';
    document.getElementById('er_cat').value = r.category || 'administrative';
    document.getElementById('er_status').value = r.status || 'open';
    document.getElementById('er_lik').value = r.likelihood || 3;
    document.getElementById('er_imp').value = r.impact || 3;
    document.getElementById('er_mit').value = r.mitigation_plan || '';
    document.getElementById('er_owner').value = r.owner_name || '';
    openModal('editRiskModal');
}
</script>
@endpush
