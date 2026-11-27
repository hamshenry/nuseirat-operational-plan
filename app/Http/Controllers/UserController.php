<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('department')->orderBy('role')->orderBy('id')->get();
        $departments = Department::orderBy('code')->get();
        return view('users.index', compact('users', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username'],
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'role' => ['required', 'in:admin,department'],
            'department_id' => ['required_if:role,department', 'nullable', 'exists:departments,id'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'must_change_password' => ['nullable', 'boolean'],
        ], [
            'username.required' => 'يرجى إدخال اسم المستخدم.',
            'username.unique' => 'اسم المستخدم محجوز مسبقاً.',
            'department_id.required_if' => 'يرجى اختيار الدائرة التابعة لهذا المستخدم.',
        ]);

        $user = User::create([
            'username' => trim($validated['username']),
            'name' => trim($validated['name']),
            'email' => $validated['email'] ?: ($validated['username'] . '@nuseirat.ps'),
            'role' => $validated['role'],
            'department_id' => $validated['role'] === 'department' ? $validated['department_id'] : null,
            'password' => Hash::make($validated['password']),
            'must_change_password' => $request->boolean('must_change_password', true),
            'is_active' => true,
        ]);

        AuditService::log('create', 'user', $user->id, "{$user->name} ({$user->username})", ['role' => $user->role]);

        return back()->with('success', "تم إنشاء حساب المستخدم «{$user->username}» بنجاح.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username,' . $user->id],
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'role' => ['required', 'in:admin,department'],
            'department_id' => ['required_if:role,department', 'nullable', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
        ]);

        if ($user->id === Auth::id() && !$request->boolean('is_active', true)) {
            return back()->with('error', 'لا يمكنك تعطيل حسابك الحالي.');
        }

        $user->update([
            'username' => trim($validated['username']),
            'name' => trim($validated['name']),
            'email' => $validated['email'] ?: $user->email,
            'role' => $validated['role'],
            'department_id' => $validated['role'] === 'department' ? $validated['department_id'] : null,
            'is_active' => $request->boolean('is_active', true),
            'must_change_password' => $request->boolean('must_change_password', false),
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);

        AuditService::log('update', 'user', $user->id, $user->username);

        return back()->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'new_password' => ['required', Password::min(8)->letters()->numbers()],
            'force_change' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => $request->boolean('force_change', true),
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);

        AuditService::log('password_change', 'user', $user->id, $user->username, ['reset_by_admin' => true]);

        return back()->with('success', "تم إعادة تعيين كلمة المرور للمستخدم «{$user->username}» بنجاح.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك النشط.');
        }

        $user->update(['deleted_by_id' => Auth::id(), 'is_active' => false]);
        $user->delete();
        AuditService::log('delete', 'user', $user->id, $user->username);

        return back()->with('success', 'تم نقل حساب المستخدم إلى سلة المحذوفات.');
    }
}
