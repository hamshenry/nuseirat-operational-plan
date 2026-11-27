@extends('layouts.app')
@section('title', 'المراسلات الداخلية')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">✉️ المراسلات الداخلية بين المشرف العام والدوائر</h2>
        <p class="page-subtitle">تبادل التوجيهات والاستفسارات والمذكرات الداخلية داخل النظام مع تأكيد القراءة</p>
    </div>
    <button type="button" class="btn btn-success" onclick="openModal('composeMsgModal')">✍️ رسالة جديدة</button>
</div>

<div class="actions-row" style="margin-bottom:16px;">
    <a href="{{ route('messages.index', ['tab' => 'inbox']) }}" class="btn {{ $tab === 'inbox' ? 'btn-primary' : 'btn-outline' }}">📥 البريد الوارد ({{ $inbox->count() }})</a>
    <a href="{{ route('messages.index', ['tab' => 'sent']) }}" class="btn {{ $tab === 'sent' ? 'btn-primary' : 'btn-outline' }}">📤 الرسائل الصادرة ({{ $sent->count() }})</a>
</div>

<div class="card">
    <div class="card-body">
        @php $list = $tab === 'sent' ? $sent : $inbox; @endphp
        @forelse($list as $msg)
            <div style="padding:15px;border:1px solid var(--border);border-right:4px solid {{ (!$msg->is_read && $tab === 'inbox') ? '#0284c7' : '#cbd5e1' }};border-radius:11px;margin-bottom:12px;background:{{ (!$msg->is_read && $tab === 'inbox') ? '#f0f9ff' : '#ffffff' }};">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
                    <div>
                        <div class="fw-bold" style="font-size:15.5px;color:var(--primary);">{{ $msg->subject }}</div>
                        <div class="text-muted" style="font-size:12.5px;margin-top:2px;">
                            @if($tab === 'inbox')
                                من: <strong>{{ $msg->sender?->name ?? 'مستخدم' }}</strong> ({{ $msg->sender?->department?->name ?? 'المشرف العام' }})
                            @else
                                إلى: <strong>{{ $msg->recipientDepartment?->name ?? $msg->recipientUser?->name ?? 'الإدارة' }}</strong>
                            @endif
                            &nbsp;|&nbsp; {{ $msg->created_at->format('Y-m-d H:i') }}
                            &nbsp;|&nbsp;
                            @if($msg->is_read)
                                <span class="badge badge-success">مقروءة</span>
                            @else
                                <span class="badge badge-warning">غير مقروءة</span>
                            @endif
                        </div>
                    </div>
                    <div class="actions-row">
                        @if($tab === 'inbox' && !$msg->is_read)
                            <form method="POST" action="{{ route('messages.read', $msg) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">✓ تعليم كمقروءة</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('messages.destroy', $msg) }}" onsubmit="return confirm('نقل الرسالة إلى سلة المحذوفات؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                        </form>
                    </div>
                </div>
                <div style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--border);white-space:pre-line;">{{ $msg->body }}</div>
            </div>
        @empty
            <p class="text-center text-muted" style="padding:32px;">لا توجد رسائل في هذا الصندوق.</p>
        @endforelse
    </div>
</div>

<!-- Compose Message Modal -->
<div class="modal-backdrop" id="composeMsgModal">
    <div class="modal-box">
        <div class="modal-header"><h3 class="modal-title">إرسال رسالة داخلية جديدة</h3><button class="modal-close" onclick="closeModal('composeMsgModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" action="{{ route('messages.store') }}">
                @csrf
                @if(auth()->user()->isAdmin())
                    <div class="form-group">
                        <label class="form-label">إرسال إلى الدائرة *</label>
                        <select name="recipient_department_id" class="form-select" required>
                            <option value="">-- اختر الدائرة المستلمة --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="form-group">
                        <label class="form-label">المستلم</label>
                        <select name="recipient_user_id" class="form-select">
                            @foreach($admins as $adm)
                                <option value="{{ $adm->id }}">{{ $adm->name }} (المشرف العام)</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group">
                    <label class="form-label">موضوع الرسالة *</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نص الرسالة *</label>
                    <textarea name="body" class="form-control" style="min-height:120px;" required></textarea>
                </div>
                <button type="submit" class="btn btn-success">📤 إرسال الرسالة</button>
            </form>
        </div>
    </div>
</div>
@endsection
