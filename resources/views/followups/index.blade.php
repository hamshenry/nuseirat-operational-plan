@extends('layouts.app')
@section('title', 'المتابعة الشهرية المتقدمة')

@section('content')
@php $viewMode = request('view', 'month'); @endphp

<div class="page-header">
    <div>
        <h2 class="page-title">📅 المتابعة الشهرية المتقدمة — {{ \App\Models\MonthlyFollowup::monthName($month) }} {{ $year->year }}</h2>
        <p class="page-subtitle">تحديث نسب الإنجاز المخطط والفعلي، الصرف المالي الشهري، القيمة المتحققة، المعوقات، ورفع أدلة الإنجاز</p>
    </div>
    <div class="actions-row">
        <a href="{{ route('followups.index', array_merge(request()->query(), ['view' => $viewMode === 'matrix' ? 'month' : 'matrix'])) }}"
           class="btn {{ $viewMode === 'matrix' ? 'btn-primary' : 'btn-outline' }}">
            {{ $viewMode === 'matrix' ? '📋 عرض جدول الشهر الحالي' : '🗓️ عرض المصفوفة السنوية (12 شهراً)' }}
        </a>
        <a href="{{ route('reports.excel', ['type' => 'monthly', 'department_id' => request('department_id')]) }}" class="btn btn-primary">📥 تصدير Excel</a>
        <a href="{{ route('reports.pdf', ['type' => 'monthly', 'department_id' => request('department_id')]) }}" class="btn btn-outline">📄 تصدير PDF</a>
    </div>
</div>

<!-- Month Tabs 1..12 + Department Filter -->
<div class="card">
    <div class="card-body" style="padding:12px 16px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                @for($m = 1; $m <= 12; $m++)
                    <a href="{{ route('followups.index', array_merge(request()->query(), ['month' => $m, 'view' => 'month'])) }}"
                       class="btn btn-sm {{ ($month === $m && $viewMode === 'month') ? 'btn-primary' : 'btn-outline' }}">
                        {{ $m }} — {{ \App\Models\MonthlyFollowup::monthName($m) }}
                    </a>
                @endfor
            </div>
            @if(auth()->user()->isAdmin())
                <form method="GET" action="{{ route('followups.index') }}" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="view" value="{{ $viewMode }}">
                    <select name="department_id" class="form-select" style="width:210px;padding:6px 10px;" onchange="this.form.submit()">
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
        <div class="kpi-label">أنشطة تم تحديثها ({{ \App\Models\MonthlyFollowup::monthName($month) }})</div>
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

@if($viewMode === 'matrix')
    <!-- Annual 12-Month Interactive Matrix View -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🗓️ المصفوفة السنوية التفاعلية للمتابعة الشهرية (اضغط على أي خلية لتحديث الشهر)</h3>
        </div>
        <div class="table-wrap">
            <table class="data-table" style="font-size:12px;">
                <thead>
                <tr>
                    <th>النشاط</th>
                    <th>الدائرة</th>
                    @for($m = 1; $m <= 12; $m++)
                        <th class="text-center" style="min-width:62px;">ش{{ $m }}</th>
                    @endfor
                </tr>
                </thead>
                <tbody>
                @forelse($activities as $act)
                    <tr>
                        <td style="min-width:200px;">
                            <a href="{{ route('activities.show', $act) }}" class="fw-bold">{{ $act->activity_code }}</a>
                            <div style="font-size:11.5px;">{{ \Illuminate\Support\Str::limit($act->title, 55) }}</div>
                        </td>
                        <td><span class="badge badge-secondary">{{ $act->department?->name }}</span></td>
                        @for($m = 1; $m <= 12; $m++)
                            @php
                                $e = $act->followups->firstWhere('month', $m);
                                $payload = [
                                    'activity_id' => $act->id,
                                    'month' => $m,
                                    'month_name' => \App\Models\MonthlyFollowup::monthName($m),
                                    'label' => $act->activity_code . ' — ' . $act->title,
                                    'planned_percent' => $e ? (float) $e->planned_percent : 0,
                                    'actual_percent' => $e ? (float) $e->actual_percent : 0,
                                    'achieved_value' => $e?->achieved_value ?? '',
                                    'actual_cost' => $e ? (float) $e->actual_cost : 0,
                                    'execution_status' => $e?->execution_status ?? 'in_progress',
                                    'achievements_note' => $e?->achievements_note ?? '',
                                    'obstacles_note' => $e?->obstacles_note ?? '',
                                    'next_steps' => $e?->next_steps ?? '',
                                ];
                                $bg = !$e ? '#f8fafc' : match($e->execution_status) {
                                    'completed' => '#dcfce7',
                                    'in_progress' => '#e0f2fe',
                                    'delayed' => '#fef3c7',
                                    'blocked' => '#fee2e2',
                                    default => '#f1f5f9',
                                };
                            @endphp
                            <td class="text-center" style="padding:4px;">
                                <button type="button"
                                        data-json="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}"
                                        onclick="openFollowupFromBtn(this)"
                                        style="width:100%;padding:6px 4px;border-radius:7px;border:1px solid #cbd5e1;background:{{ $bg }};cursor:pointer;font-family:inherit;font-weight:700;font-size:11.5px;">
                                    {{ $e ? round($e->actual_percent) . '%' : '+' }}
                                </button>
                            </td>
                        @endfor
                    </tr>
                @empty
                    <tr><td colspan="14" class="text-center text-muted" style="padding:28px;">لا توجد أنشطة لهذه السنة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
    <!-- Monthly Detailed Table View -->
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
                    <th class="text-center">المخطط / الفعلي</th>
                    <th>المتحقق والصرف</th>
                    <th class="text-center">الحالة</th>
                    <th>الإنجازات / المعوقات / الأدلة</th>
                    <th class="text-center sticky-action">الإجراء</th>
                </tr>
                </thead>
                <tbody>
                @forelse($activities as $act)
                    @php
                        $entry = $act->followups->firstWhere('month', $month);
                        $monthAttachments = $entry ? $act->attachments->where('monthly_followup_id', $entry->id) : collect();
                        $payload = [
                            'activity_id' => $act->id,
                            'month' => $month,
                            'month_name' => \App\Models\MonthlyFollowup::monthName($month),
                            'label' => $act->activity_code . ' — ' . $act->title,
                            'planned_percent' => $entry ? (float) $entry->planned_percent : 0,
                            'actual_percent' => $entry ? (float) $entry->actual_percent : 0,
                            'achieved_value' => $entry?->achieved_value ?? '',
                            'actual_cost' => $entry ? (float) $entry->actual_cost : 0,
                            'execution_status' => $entry?->execution_status ?? 'in_progress',
                            'achievements_note' => $entry?->achievements_note ?? '',
                            'obstacles_note' => $entry?->obstacles_note ?? '',
                            'next_steps' => $entry?->next_steps ?? '',
                        ];
                    @endphp
                    <tr>
                        <td class="fw-bold" style="white-space:nowrap;"><a href="{{ route('activities.show', $act) }}">{{ $act->activity_code }}</a></td>
                        <td><span class="badge badge-secondary">{{ $act->department?->name }}</span></td>
                        <td style="max-width:230px;">
                            <div class="fw-bold">{{ $act->title }}</div>
                            <div class="text-muted" style="font-size:11.5px;">المؤشر: {{ $act->kpi_name }} ({{ $act->kpi_target }})</div>
                        </td>
                        <td class="text-center" style="min-width:105px;">
                            @if($entry)
                                <div style="font-size:12px;">مخطط: <strong>{{ $entry->planned_percent }}%</strong> | فعلي: <strong style="color:#059669;">{{ $entry->actual_percent }}%</strong></div>
                                <div class="progress-track" style="margin-top:4px;"><div class="progress-fill" style="width:{{ min(100, $entry->actual_percent) }}%;"></div></div>
                            @else
                                <span class="text-muted">غير مُدخل</span>
                            @endif
                        </td>
                        <td style="white-space:nowrap;">
                            <div>{{ $entry?->achieved_value ?: '—' }}</div>
                            <div class="text-muted" style="font-size:11.5px;">صرف: <strong>{{ $entry ? number_format((float)$entry->actual_cost, 0) : '0' }}</strong> شيكل</div>
                        </td>
                        <td class="text-center">
                            @if($entry)
                                <span class="badge {{ $entry->executionBadgeClass() }}">{{ $entry->executionStatusLabel() }}</span>
                            @else
                                <span class="badge badge-secondary">لم يُسجّل</span>
                            @endif
                        </td>
                        <td style="max-width:210px;font-size:11.5px;">
                            @if($entry?->achievements_note)<div><strong>إنجاز:</strong> {{ $entry->achievements_note }}</div>@endif
                            @if($entry?->obstacles_note)<div style="color:#b91c1c;"><strong>معوقات:</strong> {{ $entry->obstacles_note }}</div>@endif
                            @foreach($monthAttachments as $ma)
                                <div style="margin-top:3px;"><a href="{{ route('attachments.download', $ma) }}" class="badge badge-info">📎 {{ $ma->title }}</a></div>
                            @endforeach
                        </td>
                        <td class="text-center sticky-action" style="white-space:nowrap;">
                            <button type="button"
                                    class="btn {{ $entry ? 'btn-info' : 'btn-success' }} btn-sm"
                                    data-json="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}"
                                    onclick="openFollowupFromBtn(this)">
                                {{ $entry ? '✏️ تعديل' : '➕ إدخال متابعة' }}
                            </button>
                            @if($entry)
                                <form method="POST" action="{{ route('followups.destroy', $entry) }}" style="display:inline;" onsubmit="return confirm('حذف سجل المتابعة لهذا الشهر؟');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted" style="padding:28px;">لا توجد أنشطة لهذه السنة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

<!-- Monthly Follow-up Modal (With Synchronized Range Sliders) -->
<div class="modal-backdrop" id="followupModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">📅 تحديث المتابعة الشهرية — <span id="fu_month_title">{{ \App\Models\MonthlyFollowup::monthName($month) }}</span></h3>
            <button type="button" class="modal-close" onclick="closeModal('followupModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="fu_act_label" style="font-weight:800;color:var(--primary);background:#f0f9ff;padding:10px 12px;border-radius:9px;margin-bottom:14px;"></div>
            <form method="POST" action="{{ route('followups.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="activity_id" id="fu_activity_id">
                <input type="hidden" name="month" id="fu_month_input" value="{{ $month }}">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">نسبة الإنجاز المخططة (%) *</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="range" min="0" max="100" step="5" id="fu_planned_range" style="flex:1;"
                                   oninput="document.getElementById('fu_planned').value=this.value">
                            <input type="number" step="0.5" min="0" max="100" name="planned_percent" id="fu_planned" class="form-control" style="width:85px;" required value="0"
                                   oninput="document.getElementById('fu_planned_range').value=this.value">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">نسبة الإنجاز الفعلية (%) *</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="range" min="0" max="100" step="5" id="fu_actual_range" style="flex:1;"
                                   oninput="document.getElementById('fu_actual').value=this.value">
                            <input type="number" step="0.5" min="0" max="100" name="actual_percent" id="fu_actual" class="form-control" style="width:85px;" required value="0"
                                   oninput="document.getElementById('fu_actual_range').value=this.value">
                        </div>
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
                        <textarea name="achievements_note" id="fu_ach" class="form-control" placeholder="اكتب ما تم إنجازه فعلياً هذا الشهر..."></textarea>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">المعوقات والتحديات</label>
                        <textarea name="obstacles_note" id="fu_obs" class="form-control" placeholder="أي معوقات واجهت التنفيذ..."></textarea>
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
                <div class="actions-row" style="margin-top:12px;">
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
function openFollowupFromBtn(btn) {
    const d = parseBtnData(btn);
    if (!d) return;
    document.getElementById('fu_activity_id').value = d.activity_id;
    document.getElementById('fu_month_input').value = d.month;
    document.getElementById('fu_month_title').textContent = d.month_name;
    document.getElementById('fu_act_label').textContent = d.label;
    document.getElementById('fu_planned').value = d.planned_percent;
    document.getElementById('fu_planned_range').value = d.planned_percent;
    document.getElementById('fu_actual').value = d.actual_percent;
    document.getElementById('fu_actual_range').value = d.actual_percent;
    document.getElementById('fu_achieved').value = d.achieved_value || '';
    document.getElementById('fu_cost').value = d.actual_cost;
    document.getElementById('fu_status').value = d.execution_status || 'in_progress';
    document.getElementById('fu_ach').value = d.achievements_note || '';
    document.getElementById('fu_obs').value = d.obstacles_note || '';
    document.getElementById('fu_next').value = d.next_steps || '';
    openModal('followupModal');
}
</script>
@endpush
