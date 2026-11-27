@extends('layouts.app')
@section('title', 'المتابعة الشهرية المتقدمة')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">المتابعة الشهرية المتقدمة — {{ \App\Models\MonthlyFollowup::monthName($month) }} {{ $year->year }}</h2>
        <p class="page-subtitle">متابعة نسب الإنجاز المخطط والفعلي، الصرف الشهري، القيمة المتحققة، المعوقات، ورفع أدلة الإنجاز الشهرية</p>
    </div>
    <div class="actions-row">
        <a href="{{ route('reports.excel', ['type' => 'monthly', 'department_id' => request('department_id')]) }}" class="btn btn-primary">📥 تصدير Excel</a>
        <a href="{{ route('reports.pdf', ['type' => 'monthly', 'department_id' => request('department_id')]) }}" class="btn btn-outline">📄 تصدير PDF</a>
    </div>
</div>

<!-- Month Tabs 1..12 -->
<div class="card">
    <div class="card-body" style="padding:12px 16px;">
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                @for($m = 1; $m <= 12; $m++)
                    <a href="{{ route('followups.index', array_merge(request()->query(), ['month' => $m])) }}"
                       class="btn btn-sm {{ $month === $m ? 'btn-primary' : 'btn-outline' }}">
                        {{ $m }} — {{ \App\Models\MonthlyFollowup::monthName($m) }}
                    </a>
                @endfor
            </div>
            @if(auth()->user()->isAdmin())
                <form method="GET" action="{{ route('followups.index') }}" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <select name="department_id" class="form-select" style="width:200px;padding:6px 10px;" onchange="this.form.submit()">
                        <option value="">جميع الدوائر</option>
                        @foreach($departments as $d)
                            <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </div>
</div>

<!-- Monthly Summary KPIs -->
<div class="grid-5" style="margin-bottom:18px;">
    <div class="kpi-card">
        <div class="kpi-label">أنشطة تم تحديثها هذا الشهر</div>
        <div class="kpi-value">{{ $summary['tracked_count'] }} <span style="font-size:14px;color:var(--text-soft);">/ {{ $activities->count() }}</span></div>
    </div>
    <div class="kpi-card kpi-sky">
        <div class="kpi-label">متوسط المخطط الشهري</div>
        <div class="kpi-value" style="color:#0284c7;">{{ $summary['avg_planned'] }}%</div>
    </div>
    <div class="kpi-card kpi-emerald">
        <div class="kpi-label">متوسط الإنجاز الفعلي</div>
        <div class="kpi-value" style="color:#059669;">{{ $summary['avg_actual'] }}%</div>
    </div>
    <div class="kpi-card kpi-purple">
        <div class="kpi-label">إجمالي الصرف هذا الشهر</div>
        <div class="kpi-value" style="font-size:21px;">{{ number_format($summary['month_spent'], 0) }} <span style="font-size:12px;">شيكل</span></div>
    </div>
    <div class="kpi-card kpi-red">
        <div class="kpi-label">مكتمل / متأخر أو متعثر</div>
        <div class="kpi-value" style="font-size:22px;"><span style="color:#059669;">{{ $summary['completed'] }}</span> / <span style="color:#dc2626;">{{ $summary['delayed_or_blocked'] }}</span></div>
    </div>
</div>

<!-- Activities Monthly Tracking Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">جدول متابعة الأنشطة — شهر {{ \App\Models\MonthlyFollowup::monthName($month) }}</h3>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>الرقم</th>
                <th>الدائرة</th>
                <th>النشاط والمؤشر</th>
                <th class="text-center">المخطط %</th>
                <th class="text-center">الفعلي %</th>
                <th>القيمة المتحققة</th>
                <th class="text-center">الصرف الشهري</th>
                <th class="text-center">حالة التنفيذ</th>
                <th>أبرز الإنجازات / المعوقات</th>
                <th class="text-center">تحديث</th>
            </tr>
            </thead>
            <tbody>
            @forelse($activities as $act)
                @php $entry = $act->followups->firstWhere('month', $month); @endphp
                <tr>
                    <td class="fw-bold"><a href="{{ route('activities.show', $act) }}">{{ $act->activity_code }}</a></td>
                    <td><span class="badge badge-secondary">{{ $act->department?->name }}</span></td>
                    <td style="min-width:220px;">
                        <div class="fw-bold">{{ $act->title }}</div>
                        <div class="text-muted" style="font-size:12px;">المؤشر: {{ $act->kpi_name }} ({{ $act->kpi_target }})</div>
                    </td>
                    <td class="text-center fw-bold">{{ $entry ? $entry->planned_percent . '%' : '—' }}</td>
                    <td class="text-center" style="min-width:110px;">
                        @if($entry)
                            <div class="fw-bold" style="color:#059669;">{{ $entry->actual_percent }}%</div>
                            <div class="progress-track"><div class="progress-fill" style="width:{{ min(100, $entry->actual_percent) }}%;"></div></div>
                        @else
                            <span class="text-muted">غير مُدخل</span>
                        @endif
                    </td>
                    <td>{{ $entry?->achieved_value ?: '—' }}</td>
                    <td class="text-center fw-bold">{{ $entry ? number_format((float)$entry->actual_cost, 2) : '0.00' }}</td>
                    <td class="text-center">
                        @if($entry)
                            <span class="badge {{ $entry->executionBadgeClass() }}">{{ $entry->executionStatusLabel() }}</span>
                        @else
                            <span class="badge badge-secondary">لم يُسجّل</span>
                        @endif
                    </td>
                    <td style="max-width:240px;font-size:12.5px;">
                        @if($entry?->achievements_note)<div><strong>إنجاز:</strong> {{ $entry->achievements_note }}</div>@endif
                        @if($entry?->obstacles_note)<div style="color:#b91c1c;"><strong>معوقات:</strong> {{ $entry->obstacles_note }}</div>@endif
                    </td>
                    <td class="text-center" style="white-space:nowrap;">
                        <button type="button" class="btn btn-primary btn-sm"
                                onclick='openFollowupModal({{ $act->id }}, @js($act->activity_code . " — " . $act->title), @json($entry))'>
                            {{ $entry ? 'تعديل' : 'إدخال متابعة' }}
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted" style="padding:28px;">لا توجد أنشطة لهذه السنة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Monthly Follow-up Modal -->
<div class="modal-backdrop" id="followupModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">تحديث المتابعة الشهرية — {{ \App\Models\MonthlyFollowup::monthName($month) }}</h3>
            <button type="button" class="modal-close" onclick="closeModal('followupModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p id="fu_act_label" style="font-weight:800;color:var(--primary);margin-top:0;"></p>
            <form method="POST" action="{{ route('followups.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="activity_id" id="fu_activity_id">
                <input type="hidden" name="month" value="{{ $month }}">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">نسبة الإنجاز المخططة (%) *</label>
                        <input type="number" step="0.5" min="0" max="100" name="planned_percent" id="fu_planned" class="form-control" required value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">نسبة الإنجاز الفعلية (%) *</label>
                        <input type="number" step="0.5" min="0" max="100" name="actual_percent" id="fu_actual" class="form-control" required value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">القيمة المتحققة للمؤشر</label>
                        <input type="text" name="achieved_value" id="fu_achieved" class="form-control" placeholder="مثال: 2 كم أو 35 موظف">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الصرف المالي الفعلي هذا الشهر (شيكل) *</label>
                        <input type="number" step="0.01" min="0" name="actual_cost" id="fu_cost" class="form-control" required value="0">
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">حالة التنفيذ *</label>
                        <select name="execution_status" id="fu_status" class="form-select" required>
                            <option value="not_started">لم يبدأ</option>
                            <option value="in_progress">قيد التنفيذ</option>
                            <option value="completed">مكتمل</option>
                            <option value="delayed">متأخر</option>
                            <option value="blocked">متعثر</option>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">أبرز الإنجازات خلال الشهر</label>
                        <textarea name="achievements_note" id="fu_ach" class="form-control"></textarea>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">المعوقات والتحديات</label>
                        <textarea name="obstacles_note" id="fu_obs" class="form-control"></textarea>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">الخطوات القادمة</label>
                        <input type="text" name="next_steps" id="fu_next" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">إرفاق دليل إنجاز لهذا الشهر (اختياري)</label>
                        <input type="file" name="evidence_file" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">وصف دليل الإنجاز</label>
                        <input type="text" name="evidence_title" class="form-control" placeholder="مثال: صور تنفيذية / محضر استلام">
                    </div>
                </div>
                <div class="actions-row" style="margin-top:10px;">
                    <button type="submit" class="btn btn-success">💾 حفظ المتابعة الشهرية</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('followupModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openFollowupModal(actId, label, entry) {
    document.getElementById('fu_activity_id').value = actId;
    document.getElementById('fu_act_label').textContent = label;
    document.getElementById('fu_planned').value = entry ? entry.planned_percent : 0;
    document.getElementById('fu_actual').value = entry ? entry.actual_percent : 0;
    document.getElementById('fu_achieved').value = entry ? (entry.achieved_value || '') : '';
    document.getElementById('fu_cost').value = entry ? entry.actual_cost : 0;
    document.getElementById('fu_status').value = entry ? entry.execution_status : 'in_progress';
    document.getElementById('fu_ach').value = entry ? (entry.achievements_note || '') : '';
    document.getElementById('fu_obs').value = entry ? (entry.obstacles_note || '') : '';
    document.getElementById('fu_next').value = entry ? (entry.next_steps || '') : '';
    openModal('followupModal');
}
</script>
@endpush
