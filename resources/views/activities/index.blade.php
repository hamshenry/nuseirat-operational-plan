@extends('layouts.app')
@section('title', 'الخطط والأنشطة التشغيلية')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">الخطط والأنشطة التشغيلية — عام {{ $year->year }}</h2>
        <p class="page-subtitle">البحث والتصفية المتقدمة، الاعتماد الفردي والجماعي، الترقيم التلقائي، والتصدير</p>
    </div>
    <div class="actions-row">
        @if($entryOpen || auth()->user()->isAdmin())
            <a href="{{ route('activities.create') }}" class="btn btn-success">➕ إدخال نشاط جديد</a>
        @endif
        @if(auth()->user()->isAdmin())
            <form method="POST" action="{{ route('activities.renumber') }}" onsubmit="return confirm('هل تريد إعادة ترقيم كافة أنشطة عام {{ $year->year }} تلقائياً وفق تسلسل البرامج؟');">
                @csrf
                <button type="submit" class="btn btn-outline">🔢 إعادة الترقيم التلقائي</button>
            </form>
        @endif
        <a href="{{ route('reports.excel', ['type' => 'plan', 'department_id' => request('department_id')]) }}" class="btn btn-primary">📥 تصدير Excel</a>
        <a href="{{ route('reports.pdf', ['type' => 'plan', 'department_id' => request('department_id')]) }}" class="btn btn-outline">📄 تصدير PDF</a>
    </div>
</div>

@if(!$entryOpen && !auth()->user()->isAdmin())
    <div class="alert alert-warning">
        <span>🔒 {{ $entryMessage }} — يمكنك استعراض الأنشطة ومتابعتها فقط خلال فترة الإغلاق.</span>
    </div>
@endif

<!-- Advanced Search & Filtering Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">🔍 البحث والتصفية المتقدمة</h3>
        @if(request()->query())
            <a href="{{ route('activities.index') }}" class="btn btn-outline btn-sm">مسح جميع الفلاتر</a>
        @endif
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('activities.index') }}">
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label">بحث نصي (رقم، نشاط، مؤشر، جهة، تحقق)</label>
                    <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="ابحث هنا...">
                </div>

                @if(auth()->user()->isAdmin())
                    <div class="form-group">
                        <label class="form-label">الدائرة</label>
                        <select name="department_id" class="form-select">
                            <option value="">جميع الدوائر</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->code }} — {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="form-group">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">جميع الحالات</option>
                        <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
                        <option value="pending" @selected(request('status') === 'pending')>قيد المراجعة</option>
                        <option value="approved" @selected(request('status') === 'approved')>معتمد</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>مرفوض</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الهدف الرئيسي</label>
                    <select name="main_objective_id" id="filter_main_id" class="form-select"
                            onchange="loadSubObjectives(this.value, 'filter_sub_id', 'filter_prog_id')">
                        <option value="">الكل</option>
                        @foreach($mainObjectives as $mo)
                            <option value="{{ $mo->id }}" @selected(request('main_objective_id') == $mo->id)>{{ $mo->code }} — {{ $mo->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الهدف الفرعي</label>
                    <select name="sub_objective_id" id="filter_sub_id" class="form-select" data-placeholder="الكل"
                            onchange="loadPrograms(this.value, 'filter_prog_id')">
                        <option value="">الكل</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">البرنامج</label>
                    <select name="program_id" id="filter_prog_id" class="form-select" data-placeholder="الكل">
                        <option value="">الكل</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الربع السنوي</label>
                    <select name="quarter" class="form-select">
                        <option value="">كل الأرباع</option>
                        <option value="q1" @selected(request('quarter') === 'q1')>الربع الأول (ر1)</option>
                        <option value="q2" @selected(request('quarter') === 'q2')>الربع الثاني (ر2)</option>
                        <option value="q3" @selected(request('quarter') === 'q3')>الربع الثالث (ر3)</option>
                        <option value="q4" @selected(request('quarter') === 'q4')>الربع الرابع (ر4)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الأهمية</label>
                    <select name="priority" class="form-select">
                        <option value="">الكل</option>
                        <option value="critical" @selected(request('priority') === 'critical')>عاجل ومهم</option>
                        <option value="high" @selected(request('priority') === 'high')>مرتفعة</option>
                        <option value="medium" @selected(request('priority') === 'medium')>متوسطة</option>
                        <option value="low" @selected(request('priority') === 'low')>منخفضة</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الجهة المساندة</label>
                    <select name="support_type" class="form-select">
                        <option value="">الكل</option>
                        <option value="internal" @selected(request('support_type') === 'internal')>داخلي</option>
                        <option value="external" @selected(request('support_type') === 'external')>خارجي</option>
                        <option value="both" @selected(request('support_type') === 'both')>داخلي وخارجي</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الحد الأدنى للتكلفة</label>
                    <input type="number" step="0.01" name="min_cost" class="form-control" value="{{ request('min_cost') }}" placeholder="0">
                </div>

                <div class="form-group">
                    <label class="form-label">الحد الأقصى للتكلفة</label>
                    <input type="number" step="0.01" name="max_cost" class="form-control" value="{{ request('max_cost') }}" placeholder="1000000">
                </div>

                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-primary" style="width:100%;">تطبيق البحث والتصفية</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Activities Table + Batch Form -->
<form method="POST" action="{{ route('activities.batch') }}" id="batchActionForm">
    @csrf
    <input type="hidden" name="action" id="batchActionType">
    <input type="hidden" name="batch_note" id="batchActionNote">

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">قائمة الأنشطة ({{ $activities->count() }} نشاط — إجمالي التكلفة: {{ number_format((float)$activities->sum('estimated_cost'), 2) }} شيكل)</h3>
            <span class="text-muted" style="font-size:12.5px;">حدد نشاطاً أو أكثر لتفعيل شريط الاعتماد والإجراءات الجماعية</span>
        </div>
        <div class="table-wrap">
            <table class="data-table" style="font-size:12.5px;">
                <thead>
                <tr>
                    <th style="width:30px;" class="text-center">
                        <input type="checkbox" id="selectAllActivities" title="تحديد الكل">
                    </th>
                    <th>الرقم</th>
                    <th>الدائرة</th>
                    <th>النشاط والهيكل الاستراتيجي</th>
                    <th>مؤشر الأداء</th>
                    <th>المسئولة / المساندة</th>
                    <th class="text-center">الأرباع</th>
                    <th class="text-center">الأهمية والتكلفة</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center sticky-action">الإجراءات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($activities as $act)
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" name="activity_ids[]" value="{{ $act->id }}" class="activity-checkbox">
                        </td>
                        <td style="white-space:nowrap;">
                            <a href="{{ route('activities.show', $act) }}" class="fw-bold" style="color:var(--primary);">{{ $act->activity_code }}</a>
                            <div style="font-size:10.5px;color:var(--text-soft);">v{{ $act->current_version }} · 📎{{ $act->attachments_count }} · 💬{{ $act->comments_count }}</div>
                        </td>
                        <td><span class="badge badge-secondary">{{ $act->department?->name }}</span></td>
                        <td style="min-width:190px;max-width:250px;">
                            <a href="{{ route('activities.show', $act) }}" class="fw-bold" style="color:var(--text);">{{ $act->title }}</a>
                            <div style="font-size:11px;color:var(--text-soft);margin-top:2px;">
                                {{ $act->mainObjective?->code }} ← {{ $act->subObjective?->code }} ← {{ $act->program?->title }}
                            </div>
                            @if($act->status === 'rejected' && $act->review_note)
                                <div style="margin-top:4px;padding:3px 7px;background:#fef2f2;border-right:3px solid #dc2626;border-radius:4px;font-size:11px;color:#991b1b;">
                                    <strong>سبب الرفض:</strong> {{ $act->review_note }}
                                </div>
                            @endif
                        </td>
                        <td style="min-width:130px;max-width:170px;">
                            <div>{{ $act->kpi_name }}</div>
                            <span class="badge badge-info" style="margin-top:3px;">{{ $act->kpi_target }}</span>
                        </td>
                        <td style="min-width:125px;max-width:165px;font-size:11.5px;">
                            <div><strong>المسئولة:</strong> {{ $act->responsible_entity }}</div>
                            @if($act->support_internal)
                                <div class="text-muted"><strong>داخلي:</strong> {{ $act->support_internal }}</div>
                            @endif
                            @if($act->support_external)
                                <div class="text-muted"><strong>خارجي:</strong> {{ $act->support_external }}</div>
                            @endif
                        </td>
                        <td class="text-center">
                            <div style="display:inline-grid;grid-template-columns:repeat(2,26px);gap:3px;">
                                <span class="q-pill {{ $act->q1 ? 'on' : 'off' }}">ر1</span>
                                <span class="q-pill {{ $act->q2 ? 'on' : 'off' }}">ر2</span>
                                <span class="q-pill {{ $act->q3 ? 'on' : 'off' }}">ر3</span>
                                <span class="q-pill {{ $act->q4 ? 'on' : 'off' }}">ر4</span>
                            </div>
                        </td>
                        <td class="text-center" style="white-space:nowrap;">
                            <span class="badge {{ $act->priorityBadgeClass() }}">{{ $act->priorityLabel() }}</span>
                            <div class="fw-bold" style="margin-top:4px;">{{ number_format((float)$act->estimated_cost, 0) }} شيكل</div>
                            <div style="font-size:10.5px;color:var(--text-soft);">{{ $act->funding_source }}</div>
                        </td>
                        <td class="text-center" style="white-space:nowrap;">
                            <span class="badge {{ $act->statusBadgeClass() }}">{{ $act->statusLabel() }}</span>
                            <div style="font-size:11px;color:#059669;margin-top:3px;">إنجاز: {{ $act->latestProgress() }}%</div>
                        </td>
                        <td class="text-center sticky-action" style="white-space:nowrap;">
                            <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:center;">
                                <a href="{{ route('activities.show', $act) }}" class="btn btn-outline btn-sm">تفاصيل</a>
                                @if(auth()->user()->isAdmin() || ($entryOpen && $act->status !== 'approved'))
                                    <a href="{{ route('activities.edit', $act) }}" class="btn btn-info btn-sm">تعديل</a>
                                @endif
                                @if(in_array($act->status, ['draft', 'rejected']) && ($entryOpen || auth()->user()->isAdmin()))
                                    <button type="button" class="btn btn-warning btn-sm"
                                            data-url="{{ route('activities.submit', $act) }}"
                                            onclick="submitSinglePost(this.dataset.url)">إرسال</button>
                                @endif
                                @if(auth()->user()->isAdmin() && $act->status !== 'approved')
                                    <button type="button" class="btn btn-success btn-sm"
                                            data-id="{{ $act->id }}" data-code="{{ $act->activity_code }}"
                                            onclick="openSingleApprove(this)">اعتماد</button>
                                @endif
                                @if(auth()->user()->isAdmin() && $act->status !== 'rejected')
                                    <button type="button" class="btn btn-danger btn-sm"
                                            data-id="{{ $act->id }}" data-code="{{ $act->activity_code }}"
                                            onclick="openSingleReject(this)">رفض</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted" style="padding:32px;">لا توجد أنشطة مطابقة لمعايير البحث الحالية.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sticky Bulk Action Bar -->
    <div class="bulk-bar" id="bulkActionBar">
        <div style="font-weight:800;">
            تم تحديد <span id="bulkSelectedCount" style="background:#10b981;padding:2px 10px;border-radius:99px;margin:0 4px;">0</span> نشاط
        </div>
        <div class="actions-row">
            @if(auth()->user()->isAdmin())
                <button type="button" class="btn btn-success btn-sm" onclick="openBatchModal('approve')">✅ اعتماد المحدد دفعة واحدة</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="openBatchModal('reject')">❌ رفض المحدد مع بيان السبب</button>
            @endif
            <button type="button" class="btn btn-warning btn-sm" onclick="openBatchModal('submit')">📤 إرسال المحدد للمراجعة</button>
            <button type="button" class="btn btn-soft-danger btn-sm" onclick="openBatchModal('delete')">🗑️ نقل لسلة المحذوفات</button>
        </div>
    </div>
</form>

<!-- Hidden helper form for single row POST submit (avoids nested forms) -->
<form method="POST" id="singlePostHelperForm" style="display:none;">@csrf</form>

<!-- Batch Decision Modal -->
<div class="modal-backdrop" id="batchDecisionModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <h3 class="modal-title" id="batchModalTitle">إجراء جماعي</h3>
            <button type="button" class="modal-close" onclick="closeModal('batchDecisionModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label" id="batchModalLabel">الملاحظة / السبب</label>
                <textarea id="batchModalTextarea" class="form-control" style="min-height:95px;"></textarea>
            </div>
            <div class="actions-row">
                <button type="button" id="batchModalConfirmBtn" class="btn btn-success" onclick="confirmBatchModal()">تأكيد</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('batchDecisionModal')">إلغاء</button>
            </div>
        </div>
    </div>
</div>

<!-- Single Approve Modal -->
<div class="modal-backdrop" id="singleApproveModal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">✅ اعتماد النشاط <span id="approveCodeLabel"></span></h3>
            <button type="button" class="modal-close" onclick="closeModal('singleApproveModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="singleApproveForm">
                @csrf
                <div class="form-group">
                    <label class="form-label">ملاحظات الاعتماد (اختياري)</label>
                    <textarea name="review_note" class="form-control" placeholder="تمت المراجعة والاعتماد..."></textarea>
                </div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-success">تأكيد الاعتماد</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('singleApproveModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Single Reject Modal (Mandatory Reason) -->
<div class="modal-backdrop" id="singleRejectModal">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title">❌ رفض النشاط <span id="rejectCodeLabel"></span> مع بيان السبب</h3>
            <button type="button" class="modal-close" onclick="closeModal('singleRejectModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="singleRejectForm">
                @csrf
                <div class="form-group">
                    <label class="form-label">سبب الرفض والتعديلات المطلوبة <span style="color:#dc2626;">*</span></label>
                    <textarea name="review_note" class="form-control" required minlength="5" placeholder="اكتب سبب الرفض بوضوح ليتم إشعار الدائرة..."></textarea>
                </div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-danger">تأكيد الرفض وإشعار الدائرة</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('singleRejectModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openSingleApprove(btn) {
    document.getElementById('approveCodeLabel').textContent = btn.dataset.code || '';
    document.getElementById('singleApproveForm').action = `/activities/${btn.dataset.id}/approve`;
    openModal('singleApproveModal');
}
function openSingleReject(btn) {
    document.getElementById('rejectCodeLabel').textContent = btn.dataset.code || '';
    document.getElementById('singleRejectForm').action = `/activities/${btn.dataset.id}/reject`;
    openModal('singleRejectModal');
}
function submitSinglePost(url) {
    const f = document.getElementById('singlePostHelperForm');
    f.action = url;
    f.submit();
}
document.addEventListener('DOMContentLoaded', () => {
    const mainVal = @json(request('main_objective_id'));
    const subVal = @json(request('sub_objective_id'));
    const progVal = @json(request('program_id'));
    if (mainVal) {
        loadSubObjectives(mainVal, 'filter_sub_id', 'filter_prog_id', subVal, progVal);
    }
});
</script>
@endpush
