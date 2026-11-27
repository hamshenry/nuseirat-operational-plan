@extends('layouts.app')
@section('title', 'النسخ الاحتياطي والاستعادة')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">💾 النسخ الاحتياطي والاستعادة</h2>
        <p class="page-subtitle">إنشاء نسخ احتياطية كاملة (قاعدة البيانات + المرفقات وأدلة الإنجاز) في ملف ZIP مضغوط مع استعادة آمنة</p>
    </div>
    <form method="POST" action="{{ route('backups.create') }}">
        @csrf
        <button type="submit" class="btn btn-success btn-lg">➕ إنشاء نسخة احتياطية الآن</button>
    </form>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title">📤 استعادة من ملف نسخة احتياطية خارجي (ZIP)</h3></div>
        <div class="card-body">
            <p class="text-muted" style="margin-top:0;font-size:13px;">
                عند الاستعادة يقوم النظام تلقائياً بإنشاء نسخة أمان احتياطية للحالة الحالية قبل استبدال البيانات.
                (محرك قاعدة البيانات الحالي: <code>{{ $dbDriver }}</code>)
            </p>
            <form method="POST" action="{{ route('backups.restore') }}" enctype="multipart/form-data" onsubmit="return confirm('هل أنت متأكد من استعادة النظام من الملف المرفوع؟');">
                @csrf
                <div class="form-group">
                    <label class="form-label">اختر ملف النسخة الاحتياطية (.zip)</label>
                    <input type="file" name="backup_file" class="form-control" accept=".zip" required>
                </div>
                <button type="submit" class="btn btn-warning">🔄 رفع واستعادة النسخة</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">🛡️ إرشادات الأمان والحفظ</h3></div>
        <div class="card-body" style="font-size:13.5px;line-height:1.8;">
            <div>• يُنصح بتنزيل نسخة احتياطية أسبوعياً وحفظها خارج الخادم.</div>
            <div>• تشمل النسخة الاحتياطية كافة الجداول، الإصدارات، سجل التعديلات، والمرفقات المرفوعة.</div>
            <div>• قبل أي عملية استعادة، ينشئ النظام ملف <code>pre_restore_safety_*.zip</code> تلقائياً لحماية البيانات.</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">النسخ الاحتياطية المحفوظة على الخادم ({{ count($backups) }})</h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>اسم الملف</th>
                <th class="text-center">الحجم</th>
                <th>تاريخ الإنشاء</th>
                <th class="text-center">الإجراءات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($backups as $b)
                <tr>
                    <td class="fw-bold"><code>{{ $b['filename'] }}</code></td>
                    <td class="text-center">{{ $b['size_human'] }}</td>
                    <td>{{ $b['created_at'] }}</td>
                    <td class="text-center" style="white-space:nowrap;">
                        <a href="{{ route('backups.download', $b['filename']) }}" class="btn btn-primary btn-sm">⬇️ تنزيل</a>
                        <form method="POST" action="{{ route('backups.restore') }}" style="display:inline;" onsubmit="return confirm('استعادة النسخة {{ $b['filename'] }}؟ سيتم حفظ نسخة أمان تلقائياً قبل الاستعادة.');">
                            @csrf
                            <input type="hidden" name="filename" value="{{ $b['filename'] }}">
                            <button type="submit" class="btn btn-warning btn-sm">↩ استعادة</button>
                        </form>
                        <form method="POST" action="{{ route('backups.destroy', $b['filename']) }}" style="display:inline;" onsubmit="return confirm('حذف ملف النسخة الاحتياطية؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted" style="padding:28px;">لم يتم إنشاء نسخ احتياطية بعد. اضغط على «إنشاء نسخة احتياطية الآن».</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
