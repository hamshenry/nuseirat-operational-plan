<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — بلدية النصيرات | نظام إدارة الخطة التشغيلية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <img src="{{ asset('images/logo.png') }}" alt="شعار بلدية النصيرات" class="auth-logo">
        <div style="text-align:center;margin-bottom:22px;">
            <h1 style="margin:0;font-size:21px;font-weight:800;color:#0f3644;">بلدية النصيرات</h1>
            <p style="margin:4px 0 0;color:#475569;font-weight:600;font-size:14px;">نظام إدارة الخطة التشغيلية والمتابعة</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="username">اسم المستخدم</label>
                <input id="username" type="text" name="username" class="form-control" value="{{ old('username') }}" required autofocus placeholder="مثال: admin أو legal">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">كلمة المرور</label>
                <input id="password" type="password" name="password" class="form-control" required placeholder="••••••••">
            </div>

            <div class="form-group" style="display:flex;align-items:center;justify-content:space-between;">
                <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                    <input type="checkbox" name="remember" value="1">
                    <span>تذكرني على هذا الجهاز</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:6px;">
                تسجيل الدخول
            </button>
        </form>

        <div style="margin-top:20px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;color:#475569;">
            <div style="font-weight:800;color:#0f3644;margin-bottom:4px;">حسابات تجريبية جاهزة:</div>
            <div>• المشرف العام: <strong>admin</strong> / كلمة المرور: <strong>Admin@1234</strong></div>
            <div>• الدوائر (تغيير كلمة المرور عند أول دخول): <strong>legal</strong>، <strong>admin_dept</strong>، <strong>engineering</strong>، <strong>finance</strong>، <strong>health</strong> / <strong>Dept@1234</strong></div>
        </div>
    </div>
</div>
</body>
</html>
