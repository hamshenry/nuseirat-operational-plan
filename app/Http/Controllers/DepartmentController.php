<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::with(['users'])
            ->withCount(['activities', 'operationalPlans'])
            ->orderBy('code')
            ->get();

        return view('departments.index', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:200'],
            'manager_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'create_account' => ['nullable', 'boolean'],
            'username' => ['required_if:create_account,1', 'nullable', 'string', 'max:80', 'alpha_dash', 'unique:users,username'],
            'account_full_name' => ['nullable', 'string', 'max:180'],
            'password' => ['required_if:create_account,1', 'nullable', Password::min(8)->letters()->numbers()],
        ], [
            'code.required' => 'يرجى إدخال رمز الدائرة.',
            'code.unique' => 'رمز الدائرة مستخدم مسبقاً.',
            'name.required' => 'يرجى إدخال اسم الدائرة.',
            'username.required_if' => 'يرجى إدخال اسم المستخدم لحساب الدائرة.',
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
            'password.required_if' => 'يرجى إدخال كلمة المرور الأولية لحساب الدائرة.',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $dept = Department::create([
                'code' => trim($validated['code']),
                'name' => trim($validated['name']),
                'manager_name' => $validated['manager_name'] ?? '',
                'email' => $validated['email'] ?? '',
                'phone' => $validated['phone'] ?? '',
                'description' => $validated['description'] ?? '',
            ]);

            AuditService::log('create', 'department', $dept->id, $dept->name, ['code' => $dept->code]);

            if ($request->boolean('create_account') && !empty($validated['username'])) {
                $user = User::create([
                    'username' => trim($validated['username']),
                    'name' => trim($validated['account_full_name'] ?: ('مسؤول ' . $dept->name)),
                    'email' => $validated['email'] ?: ($validated['username'] . '@nuseirat.ps'),
                    'password' => Hash::make($validated['password']),
                    'role' => 'department',
                    'department_id' => $dept->id,
                    'must_change_password' => true,
                    'is_active' => true,
                ]);
                AuditService::log('create', 'user', $user->id, $user->username, ['department_id' => $dept->id]);
            }
        });

        return back()->with('success', 'تم إضافة الدائرة وإنشاء حساب الدخول الخاص بها بنجاح (سيُطلب تغيير كلمة المرور عند أول دخول).');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:departments,code,' . $department->id],
            'name' => ['required', 'string', 'max:200'],
            'manager_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'entry_override_mode' => ['nullable', 'in:inherit,open,closed'],
            'entry_override_until' => ['nullable', 'date'],
        ]);

        $override = match ($validated['entry_override_mode'] ?? 'inherit') {
            'open' => true,
            'closed' => false,
            default => null,
        };

        $department->update([
            'code' => trim($validated['code']),
            'name' => trim($validated['name']),
            'manager_name' => $validated['manager_name'] ?? '',
            'email' => $validated['email'] ?? '',
            'phone' => $validated['phone'] ?? '',
            'description' => $validated['description'] ?? '',
            'entry_override_open' => $override,
            'entry_override_until' => $override === true ? ($validated['entry_override_until'] ?? null) : null,
        ]);

        AuditService::log('update', 'department', $department->id, $department->name, ['override' => $override]);

        return back()->with('success', 'تم تحديث بيانات الدائرة بنجاح.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->update(['deleted_by_id' => Auth::id()]);
        $department->delete();
        AuditService::log('delete', 'department', $department->id, $department->name);

        return back()->with('success', 'تم نقل الدائرة إلى سلة المحذوفات بنجاح.');
    }
}
