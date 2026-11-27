<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة المرور لأول مرة — بلدية النصيرات</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-page">
    <div class="auth-card" style="max-width:480px;">
        <img src="{{ asset('images/logo.png') }}" alt="شعار بلدية النصيرات" class="auth-logo" style="width:76px;height:76px;">
        <div style="text-align:center;margin-bottom:18px;">
            <h1 style="margin:0;font-size:20px;font-weight:800;color:#0f3644;">تغيير كلمة المرور الافتراضية</h1>
            <p style="margin:4px 0 0;color:#475569;font-size:13.5px;">
                مرحباً <strong>{{ auth()->user()->name }}</strong> — لحماية حساب دائرتك، يرجى تعيين كلمة مرور شخصية جديدة قبل الدخول للنظام.
            </p>
        </div>

        @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin:0;padding-right:16px;">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.first.update') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">كلمة المرور الحالية (المؤقتة)</label>
                <input type="password" name="current_password" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور الجديدة (8 أحرف على الأقل تشمل حروفاً وأرقاماً)</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                <input type="password" name="new_password_confirmation" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-success btn-lg" style="width:100%;">
                حفظ كلمة المرور والمتابعة للنظام
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top:12px;text-align:center;">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">تسجيل الخروج</button>
        </form>
    </div>
</div>
</body>
</html>
