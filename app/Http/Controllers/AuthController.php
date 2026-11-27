<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'يرجى إدخال اسم المستخدم.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
        ]);

        $username = trim($credentials['username']);
        $user = User::where('username', $username)->first();

        if (!$user) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'بيانات الدخول غير صحيحة.']);
        }

        if (!$user->is_active) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'هذا الحساب معطّل حالياً. يرجى مراجعة المشرف العام.']);
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            $minutes = max(1, (int) ceil(now()->diffInSeconds($user->locked_until) / 60));
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => "تم تجميد الحساب مؤقتاً بسبب محاولات دخول فاشلة متكررة. حاول مجدداً بعد {$minutes} دقيقة."]);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            $attempts = (int) $user->failed_login_attempts + 1;
            if ($attempts >= 5) {
                $user->update([
                    'failed_login_attempts' => 0,
                    'locked_until' => now()->addMinutes(15),
                ]);
                AuditService::log('login_locked', 'user', $user->id, $user->username, ['reason' => '5 failed attempts'], $user);
                return back()
                    ->withInput($request->only('username'))
                    ->withErrors(['username' => 'تم تجميد الحساب لمدة 15 دقيقة بعد 5 محاولات دخول فاشلة.']);
            }
            $user->update(['failed_login_attempts' => $attempts]);
            $remaining = 5 - $attempts;
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => "اسم المستخدم أو كلمة المرور غير صحيحة. (المتبقي {$remaining} محاولات)"]);
        }

        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ]);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        AuditService::log('login', 'user', $user->id, $user->name, ['role' => $user->role], $user);

        if ($user->must_change_password) {
            return redirect()->route('password.first')
                ->with('warning', 'مرحباً بك! يرجى تغيير كلمة المرور الافتراضية لتأمين حسابك قبل المتابعة.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            AuditService::log('logout', 'user', $user->id, $user->name, [], $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'تم تسجيل الخروج بنجاح.');
    }

    public function showFirstPasswordChange(): View|RedirectResponse
    {
        $user = Auth::user();
        if (!$user->must_change_password) {
            return redirect()->route('dashboard');
        }
        return view('auth.first-password-change');
    }

    public function updateFirstPassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'يرجى إدخال كلمة المرور الحالية.',
            'new_password.required' => 'يرجى إدخال كلمة المرور الجديدة.',
            'new_password.confirmed' => 'تأكيد كلمة المرور الجديدة غير متطابق.',
            'new_password.min' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل وتحتوي على حروف وأرقام.',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
        }

        if (Hash::check($validated['new_password'], $user->password)) {
            return back()->withErrors(['new_password' => 'يجب اختيار كلمة مرور جديدة مختلفة عن الكلمة الافتراضية.']);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => false,
        ]);

        $request->session()->regenerate();
        AuditService::log('password_change', 'user', $user->id, $user->name, ['first_login' => true], $user);

        return redirect()->route('dashboard')->with('success', 'تم تحديث كلمة المرور بنجاح. أهلاً بك في نظام إدارة الخطة التشغيلية!');
    }

    public function showProfile(): View
    {
        return view('auth.profile', ['user' => Auth::user()->load('department')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'يرجى إدخال كلمة المرور الحالية.',
            'new_password.required' => 'يرجى إدخال كلمة المرور الجديدة.',
            'new_password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => false,
        ]);

        AuditService::log('password_change', 'user', $user->id, $user->name, ['first_login' => false], $user);

        return back()->with('success', 'تم تغيير كلمة المرور بنجاح.');
    }
}
