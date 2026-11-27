@extends('layouts.app')
@section('title', 'تفاصيل النشاط ' . $activity->activity_code)

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">
            النشاط {{ $activity->activity_code }}
            <span class="badge {{ $activity->statusBadgeClass() }}" style="font-size:13px;vertical-align:middle;">{{ $activity->statusLabel() }}</span>
        </h2>
        <p class="page-subtitle">{{ $activity->department?->name }} | عام {{ $activity->planningYear?->year }} | الإصدار الحالي: v{{ $activity->current_version }}</p>
    </div>
    <div class="actions-row">
        <a href="{{ route('activities.index') }}" class="btn btn-outline">↩ قائمة الأنشطة</a>
        @if(auth()->user()->isAdmin() || ($entryOpen && $activity->status !== 'approved'))
            <a href="{{ route('activities.edit', $activity) }}" class="btn btn-info">✏️ تعديل</a>
        @endif
        @if(in_array($activity->status, ['draft', 'rejected']) && ($entryOpen || auth()->user()->isAdmin()))
            <form method="POST" action="{{ route('activities.submit', $activity) }}">
                @csrf
                <button type="submit" class="btn btn-warning">📤 إرسال للاعتماد</button>
            </form>
        @endif
        @if(auth()->user()->isAdmin() || ($entryOpen && $activity->status !== 'approved'))
            <form method="POST" action="{{ route('activities.destroy', $activity) }}" onsubmit="return confirm('نقل هذا النشاط إلى سلة المحذوفات؟');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-soft-danger">🗑️ حذف</button>
            </form>
        @endif
    </div>
</div>

@if($activity->review_note)
    <div class="alert {{ $activity->status === 'rejected' ? 'alert-danger' : 'alert-success' }}">
        <div>
            <strong>{{ $activity->status === 'rejected' ? 'سبب الرفض من المشرف العام:' : 'ملاحظة الاعتماد:' }}</strong>
            {{ $activity->review_note }}
            @if($activity->reviewer)
                <span style="font-size:12px;opacity:0.8;">({{ $activity->reviewer->name }} — {{ $activity->reviewed_at?->format('Y-m-d H:i') }})</span>
            @endif
        </div>
    </div>
@endif

<!-- Admin Single Approval / Rejection Box -->
@if(auth()->user()->isAdmin())
    <div class="card" style="border-right:4px solid var(--primary);">
        <div class="card-header"><h3 class="card-title">⚖️ قرار المشرف العام (اعتماد أو رفض مع بيان السبب)</h3></div>
        <div class="card-body">
            <div class="grid-2">
                <form method="POST" action="{{ route('activities.approve', $activity) }}" style="background:#f0fdf4;padding:14px;border-radius:10px;border:1px solid #bbf7d0;">
                    @csrf
                    <label class="form-label">اعتماد النشاط (ملاحظة اختيارية)</label>
                    <input type="text" name="review_note" class="form-control" placeholder="مثال: تمت المراجعة والمصادقة على النشاط">
                    <button type="submit" class="btn btn-success" style="margin-top:10px;">✅ اعتماد النشاط</button>
                </form>
                <form method="POST" action="{{ route('activities.reject', $activity) }}" style="background:#fef2f2;padding:14px;border-radius:10px;border:1px solid #fecaca;">
                    @csrf
                    <label class="form-label">رفض النشاط — بيان السبب (إلزامي) *</label>
                    <input type="text" name="review_note" class="form-control" required minlength="5" placeholder="اكتب سبب الرفض والتعديل المطلوب من الدائرة...">
                    <button type="submit" class="btn btn-danger" style="margin-top:10px;">❌ رفض النشاط وإشعار الدائرة</button>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- Activity Core Details -->
<div class="card">
    <div class="card-header"><h3 class="card-title">بطاقة بيانات النشاط التشغيلي</h3></div>
    <div class="card-body">
        <table class="data-table">
            <tr><th style="width:200px;">الهدف الرئيسي</th><td>{{ $activity->mainObjective?->code }} — {{ $activity->mainObjective?->title }}</td></tr>
            <tr><th>الهدف الفرعي</th><td>{{ $activity->subObjective?->code }} — {{ $activity->subObjective?->title }}</td></tr>
            <tr><th>البرنامج</th><td>{{ $activity->program?->code }} — {{ $activity->program?->title }}</td></tr>
            <tr><th>النشاط</th><td class="fw-bold">{{ $activity->title }}</td></tr>
            <tr><th>مؤشر الأداء</th><td>{{ $activity->kpi_name }}</td></tr>
            <tr><th>قيمة المؤشر المستهدفة</th><td><span class="badge badge-info">{{ $activity->kpi_target }}</span></td></tr>
            <tr><th>وسائل التحقق</th><td>{{ $activity->verification_means }}</td></tr>
            <tr><th>الجهة المسئولة</th><td>{{ $activity->responsible_entity }}</td></tr>
            <tr>
                <th>الجهة المساندة</th>
                <td>
                    <span class="badge badge-secondary">{{ $activity->supportTypeLabel() }}</span>
                    @if($activity->support_internal) | <strong>داخلي:</strong> {{ $activity->support_internal }} @endif
                    @if($activity->support_external) | <strong>خارجي:</strong> {{ $activity->support_external }} @endif
                </td>
            </tr>
            <tr>
                <th>الأرباع التنفيذية</th>
                <td>
                    <span class="q-pill {{ $activity->q1 ? 'on' : 'off' }}">ر1</span>
                    <span class="q-pill {{ $activity->q2 ? 'on' : 'off' }}">ر2</span>
                    <span class="q-pill {{ $activity->q3 ? 'on' : 'off' }}">ر3</span>
                    <span class="q-pill {{ $activity->q4 ? 'on' : 'off' }}">ر4</span>
                </td>
            </tr>
            <tr>
                <th>الأهمية والتكلفة</th>
                <td>
                    <span class="badge {{ $activity->priorityBadgeClass() }}">{{ $activity->priorityLabel() }}</span>
                    &nbsp;|&nbsp; <strong>التكلفة التقديرية:</strong> {{ number_format((float)$activity->estimated_cost, 2) }} شيكل
                    &nbsp;|&nbsp; <strong>مصدر التمويل:</strong> {{ $activity->funding_source }}
                    &nbsp;|&nbsp; <strong>الصرف الفعلي:</strong> {{ number_format($activity->totalActualSpent(), 2) }} شيكل
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- Attachments & Evidence + Comments -->
<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title">📎 المرفقات وأدلة الإنجاز ({{ $activity->attachments->count() }})</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('attachments.store', $activity) }}" enctype="multipart/form-data" style="margin-bottom:16px;padding:12px;background:var(--surface-soft);border-radius:10px;">
                @csrf
                <div class="form-group">
                    <label class="form-label">وصف المرفق أو دليل الإنجاز</label>
                    <input type="text" name="title" class="form-control" required placeholder="مثال: محضر الاستلام، صور ميدانية، تقرير شهر مارس">
                </div>
                <div class="form-group">
                    <label class="form-label">الملف (PDF, صور, Word, Excel — حد أقصى 15MB)</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">⬆️ رفع الدليل / المرفق</button>
            </form>

            @forelse($activity->attachments as $att)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border:1px solid var(--border);border-radius:9px;margin-bottom:8px;">
                    <div>
                        <div class="fw-bold">{{ $att->title }}</div>
                        <div style="font-size:11.5px;color:var(--text-soft);">{{ $att->original_filename }} ({{ $att->formattedSize() }}) — {{ $att->created_at->format('Y-m-d') }}</div>
                    </div>
                    <div class="actions-row">
                        <a href="{{ route('attachments.download', $att) }}" class="btn btn-outline btn-sm">تنزيل</a>
                        <form method="POST" action="{{ route('attachments.destroy', $att) }}" onsubmit="return confirm('حذف هذا المرفق؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center">لم يتم رفع أدلة إنجاز لهذا النشاط بعد.</p>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">💬 التعليقات والمناقشات الداخلية ({{ $activity->comments->count() }})</h3></div>
        <div class="card-body">
            <div style="max-height:280px;overflow-y:auto;margin-bottom:14px;">
                @forelse($activity->comments as $c)
                    <div style="padding:10px 12px;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;background:{{ $c->user?->isAdmin() ? '#f0f9ff' : '#f8fafc' }};">
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                            <strong>{{ $c->user?->name ?? 'مستخدم' }} ({{ $c->user?->roleLabel() }})</strong>
                            <span class="text-muted">{{ $c->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                        <div>{{ $c->body }}</div>
                        @if(auth()->user()->isAdmin() || $c->user_id === auth()->id())
                            <form method="POST" action="{{ route('comments.destroy', $c) }}" style="margin-top:6px;">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:11px;">حذف</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted text-center">لا توجد تعليقات بعد.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('comments.store', $activity) }}">
                @csrf
                <div class="form-group">
                    <textarea name="body" class="form-control" required placeholder="أضف تعليقاً أو استفساراً حول هذا النشاط..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">إرسال التعليق</button>
            </form>
        </div>
    </div>
</div>

<!-- Version History & Side-by-Side Diff Comparison -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">🕒 سجل التعديلات ومقارنة الإصدارات ({{ $versions->count() }} إصدار)</h3>
    </div>
    <div class="card-body">
        <div class="table-wrap" style="margin-bottom:16px;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>الإصدار</th>
                    <th>ملخص التعديل</th>
                    <th>تم بواسطة</th>
                    <th>التاريخ والوقت</th>
                    <th class="text-center">استعادة</th>
                </tr>
                </thead>
                <tbody>
                @foreach($versions as $ver)
                    <tr>
                        <td><span class="badge badge-info">v{{ $ver->version_number }}</span></td>
                        <td>{{ $ver->change_summary }}</td>
                        <td>{{ $ver->changed_by_name }}</td>
                        <td>{{ $ver->created_at->format('Y-m-d H:i') }}</td>
                        <td class="text-center">
                            @if($ver->version_number !== $activity->current_version)
                                <form method="POST" action="{{ route('activities.versions.restore', [$activity, $ver]) }}" onsubmit="return confirm('استعادة محتوى الإصدار v{{ $ver->version_number }}؟');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">↩ استعادة هذا الإصدار</button>
                                </form>
                            @else
                                <span class="badge badge-success">الإصدار الحالي</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if($versions->count() >= 2)
            <div style="padding:14px;background:var(--surface-soft);border-radius:10px;border:1px solid var(--border);">
                <form method="GET" action="{{ route('activities.show', $activity) }}" class="actions-row" style="margin-bottom:14px;">
                    <strong>مقارنة إصدارين:</strong>
                    <select name="v1" class="form-select" style="width:auto;">
                        @foreach($versions as $v)
                            <option value="{{ $v->id }}" @selected($vOld && $vOld->id === $v->id)>الإصدار الأقدم: v{{ $v->version_number }} ({{ $v->created_at->format('Y-m-d') }})</option>
                        @endforeach
                    </select>
                    <span>مقابل</span>
                    <select name="v2" class="form-select" style="width:auto;">
                        @foreach($versions as $v)
                            <option value="{{ $v->id }}" @selected($vNew && $vNew->id === $v->id)>الإصدار الأحدث: v{{ $v->version_number }} ({{ $v->created_at->format('Y-m-d') }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">عرض الفروقات</button>
                </form>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>الحقل</th>
                            <th>الإصدار v{{ $vOld?->version_number }}</th>
                            <th>الإصدار v{{ $vNew?->version_number }}</th>
                            <th class="text-center">التغيير</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($diffRows as $row)
                            <tr class="{{ $row['changed'] ? 'diff-changed' : '' }}">
                                <td class="fw-bold">{{ $row['label'] }}</td>
                                <td>@if($row['changed'])<span class="diff-old">{{ $row['old'] }}</span>@else{{ $row['old'] }}@endif</td>
                                <td>@if($row['changed'])<span class="diff-new">{{ $row['new'] }}</span>@else{{ $row['new'] }}@endif</td>
                                <td class="text-center">
                                    @if($row['changed'])
                                        <span class="badge badge-warning">تم التعديل</span>
                                    @else
                                        <span class="text-muted">بدون تغيير</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
