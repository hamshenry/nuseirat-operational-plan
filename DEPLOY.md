# دليل تشغيل وتأمين ونشر نظام إدارة الخطة التشغيلية — بلدية النصيرات (Laravel)

## 1. التشغيل المحلي السريع (Windows / WAMP / XAMPP)

```bash
cd C:\Users\Marshal\.gemini\antigravity\scratch\nuseirat-operational-plan
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

ثم افتح المتصفح على: `http://127.0.0.1:8000`

### حسابات الدخول الافتراضية
- **المشرف العام:** `admin` / كلمة المرور: `Admin@1234`
- **حسابات الدوائر (تُطلب منها تغيير كلمة المرور تلقائياً عند أول دخول):**
  - الدائرة القانونية: `legal` / `Dept@1234`
  - الشؤون الإدارية: `admin_dept` / `Dept@1234`
  - الهندسة والمشاريع: `engineering` / `Dept@1234`
  - الدائرة المالية: `finance` / `Dept@1234`
  - الصحة والبيئة: `health` / `Dept@1234`

> **مهم قبل النشر الفعلي:** غيّر كلمة مرور `admin` فوراً من قائمة الملف الشخصي.

---

## 2. ميزات الأمان المدمجة في النظام
- تشفير كلمات المرور بخوارزمية **Bcrypt (12 جولة)**.
- إلزام كل دائرة بتغيير كلمة المرور الافتراضية عند **أول تسجيل دخول** عبر Middleware مخصص (`EnsurePasswordChanged`).
- حماية من هجمات تخمين كلمات المرور (Rate Limiting + تجميد مؤقت للحساب 15 دقيقة بعد 5 محاولات فاشلة).
- حماية CSRF كاملة لجميع النماذج والعمليات وتجديد الجلسة (Session Regeneration) عند تسجيل الدخول والخروج.
- عزل كامل للصلاحيات (RBAC): الدائرة لا يمكنها الوصول أو تعديل أو تنزيل مرفقات دائرة أخرى.
- حفظ المرفقات خارج مجلد `public/` داخل `storage/app/private/attachments/` وتنزيلها فقط عبر تحقق الصلاحية، مع فلترة صارمة لامتدادات وحجم الملفات (15MB).
- رؤوس الأمان HTTP (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security`).
- سجل تدقيقي شامل (`audit_logs`) ونسخ احتياطي مضغوط مع نسخة أمان تلقائية قبل أي استعادة.

---

## 3. النشر عبر الإنترنت (Production)

### الخيار أ: النشر عبر Docker (الأسرع والأكثر استقراراً)
1. انسخ `.env.production.example` إلى `.env` وحدّث `APP_URL` وبيانات قاعدة البيانات.
2. نفّذ:
```bash
docker compose up -d --build
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --seed --force
docker compose exec app php artisan optimize
```
3. ضع Nginx أو Caddy أمام المنفذ `8000` مع شهادة SSL مجانية (Let's Encrypt).

### الخيار ب: النشر على استضافة مشتركة (cPanel) أو VPS (Ubuntu + Nginx)
1. ارفع ملفات المشروع، واجعل مجلد الجذر للموقع (`DocumentRoot`) يشير إلى مجلد **`/public`** فقط (لحماية `.env` و`storage/`).
2. ثبّت الحزم: `composer install --no-dev --optimize-autoloader`
3. انسخ `.env.production.example` إلى `.env` وأنشئ مفتاح التشفير:
```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
4. تأكد من تفعيل HTTPS وضبط `APP_DEBUG=false` و`SESSION_SECURE_COOKIE=true`.
5. فعّل أذونات الكتابة لمجلدات `storage/` و`bootstrap/cache/`.
