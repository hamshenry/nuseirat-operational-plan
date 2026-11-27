@extends('layouts.app')
@section('title', 'الإشعارات الداخلية')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">🔔 مركز الإشعارات الداخلية</h2>
        <p class="page-subtitle">متابعة إشعارات الاعتماد والرفض والتعليقات وفتح/إغلاق فترات الإدخال والتنبيهات</p>
    </div>
    <form method="POST" action="{{ route('notifications.read_all') }}">
        @csrf
        <button type="submit" class="btn btn-primary">✓ تعليم الكل كمقروء</button>
    </form>
</div>

<div class="card">
    <div class="card-body">
        @forelse($notifications as $n)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;border:1px solid var(--border);border-right:4px solid {{ $n->is_read ? '#cbd5e1' : '#059669' }};border-radius:10px;margin-bottom:10px;background:{{ $n->is_read ? '#ffffff' : '#f0fdf4' }};">
                <div>
                    <div class="fw-bold" style="color:var(--primary);">{{ $n->title }} @if(!$n->is_read)<span class="badge badge-success">جديد</span>@endif</div>
                    <div style="margin-top:3px;">{{ $n->message }}</div>
                    <div class="text-muted" style="font-size:11.5px;margin-top:4px;">{{ $n->created_at->format('Y-m-d H:i') }}</div>
                </div>
                <form method="POST" action="{{ route('notifications.read', $n) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">{{ $n->link_url ? 'فتح التفاصيل' : 'تمت القراءة' }}</button>
                </form>
            </div>
        @empty
            <p class="text-center text-muted" style="padding:32px;">لا توجد إشعارات حالياً.</p>
        @endforelse
    </div>
</div>
@endsection
