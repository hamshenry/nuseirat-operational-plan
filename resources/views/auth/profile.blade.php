@extends('layouts.app')
@section('title', 'الملف الشخصي وتغيير كلمة المرور')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">الملف الشخصي والأمان</h2>
        <p class="page-subtitle">عرض بيانات الحساب وتحديث كلمة المرور</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title">بيانات الحساب</h3></div>
        <div class="card-body">
            <table class="data-table">
                <tr><th>الاسم الكامل</th><td>{{ $user->name }}</td></tr>
                <tr><th>اسم المستخدم</th><td><code>{{ $user->username }}</code></td></tr>
                <tr><th>الصلاحية</th><td><span class="badge badge-info">{{ $user->roleLabel() }}</span></td></tr>
                <tr><th>الدائرة</th><td>{{ $user->department?->name ?? 'الإدارة العليا (المشرف العام)' }}</td></tr>
                <tr><th>البريد الإلكتروني</th><td>{{ $user->email ?: '—' }}</td></tr>
                <tr><th>آخر تسجيل دخول</th><td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">تغيير كلمة المرور</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">كلمة المرور الحالية</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" class="form-control" required minlength="8" placeholder="8 أحرف على الأقل (حروف + أرقام)">
                </div>
                <div class="form-group">
                    <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                    <input type="password" name="new_password_confirmation" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary">تحديث كلمة المرور</button>
            </form>
        </div>
    </div>
</div>
@endsection
