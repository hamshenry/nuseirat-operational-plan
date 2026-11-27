@extends('layouts.app')
@section('title', 'سجل التعديلات ومقارنة الإصدارات')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">📜 سجل التعديلات ومقارنة الإصدارات</h2>
        <p class="page-subtitle">سجل تدقيقي شامل لجميع العمليات في النظام مع وصول مباشر لمقارنة إصدارات الأنشطة المعدّلة</p>
    </div>
</div>

@if($versionedActivities->count())
<div class="card">
    <div class="card-header"><h3 class="card-title">🔄 الأنشطة التي تحتوي على عدة إصدارات (مقارنة فورية)</h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>رقم النشاط</th>
                <th>الدائرة</th>
                <th>النشاط</th>
                <th class="text-center">عدد الإصدارات</th>
                <th>آخر تحديث</th>
                <th class="text-center">مقارنة الإصدارات</th>
            </tr>
            </thead>
            <tbody>
            @foreach($versionedActivities as $act)
                <tr>
                    <td class="fw-bold">{{ $act->activity_code }}</td>
                    <td>{{ $act->department?->name }}</td>
                    <td>{{ $act->title }}</td>
                    <td class="text-center"><span class="badge badge-info">{{ $act->versions_count }} إصدارات (v{{ $act->current_version }})</span></td>
                    <td>{{ $act->updated_at->format('Y-m-d H:i') }}</td>
                    <td class="text-center">
                        <a href="{{ route('activities.show', $act) }}" class="btn btn-primary btn-sm">🔍 مقارنة الفروقات</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">
        <h3 class="card-title">سجل التدقيق الكامل</h3>
        <form method="GET" action="{{ route('audit.index') }}" class="actions-row">
            <input type="text" name="q" class="form-control" style="width:200px;" value="{{ request('q') }}" placeholder="بحث بالمستخدم أو الوصف...">
            <select name="action" class="form-select" style="width:160px;">
                <option value="">كل العمليات</option>
                @foreach(['create'=>'إنشاء','update'=>'تعديل','delete'=>'حذف','restore'=>'استعادة','approve'=>'اعتماد','reject'=>'رفض','batch_approve'=>'اعتماد جماعي','login'=>'دخول','export'=>'تصدير','clone_year'=>'نسخ سنة','backup'=>'نسخ احتياطي'] as $k => $v)
                    <option value="{{ $k }}" @selected(request('action') === $k)>{{ $v }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">بحث</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>التاريخ والوقت</th>
                <th>المستخدم</th>
                <th class="text-center">العملية</th>
                <th>النوع</th>
                <th>العنصر / التفاصيل</th>
                <th>عنوان IP</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td style="white-space:nowrap;">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td><strong>{{ $log->full_name }}</strong> <span class="text-muted">({{ $log->username }})</span></td>
                    <td class="text-center"><span class="badge badge-info">{{ $log->actionLabel() }}</span></td>
                    <td><code>{{ $log->entity_type }}</code></td>
                    <td>{{ $log->entity_label ?: '—' }}</td>
                    <td><code>{{ $log->ip_address ?: '—' }}</code></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">لا توجد سجلات مطابقة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $logs->links() }}</div>
</div>
@endsection
