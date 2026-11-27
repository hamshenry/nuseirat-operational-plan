@extends('layouts.app')
@section('title', 'إدارة المستخدمين والصلاحيات')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">👥 إدارة المستخدمين والصلاحيات</h2>
        <p class="page-subtitle">إنشاء حسابات المشرفين والدوائر، إعادة تعيين كلمات المرور، وتفعيل تغيير كلمة المرور عند الدخول الأول</p>
    </div>
    <button type="button" class="btn btn-success" onclick="openModal('createUserModal')">➕ مستخدم جديد</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>اسم المستخدم</th>
                <th>الاسم الكامل</th>
                <th>الصلاحية</th>
                <th>الدائرة</th>
                <th class="text-center">حالة الحساب</th>
                <th class="text-center">كلمة المرور</th>
                <th>آخر دخول</th>
                <th class="text-center">الإجراءات</th>
            </tr>
            </thead>
            <tbody>
            @foreach($users as $u)
                @php
                    $userData = [
                        'id' => $u->id,
                        'username' => $u->username,
                        'name' => $u->name,
                        'role' => $u->role,
                        'department_id' => $u->department_id,
                        'email' => $u->email ?? '',
                        'is_active' => (bool) $u->is_active,
                        'must_change_password' => (bool) $u->must_change_password,
                    ];
                @endphp
                <tr>
                    <td><code>{{ $u->username }}</code></td>
                    <td class="fw-bold">{{ $u->name }}</td>
                    <td><span class="badge {{ $u->isAdmin() ? 'badge-purple' : 'badge-info' }}">{{ $u->roleLabel() }}</span></td>
                    <td>{{ $u->department?->name ?? 'الإدارة العليا' }}</td>
                    <td class="text-center">
                        <span class="badge {{ $u->is_active ? 'badge-success' : 'badge-danger' }}">{{ $u->is_active ? 'نشط' : 'معطّل' }}</span>
                        @if($u->locked_until && $u->locked_until->isFuture())
                            <span class="badge badge-danger">مجمّد مؤقتاً</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($u->must_change_password)
                            <span class="badge badge-warning">مطلوب تغييرها</span>
                        @else
                            <span class="badge badge-secondary">محدّثة</span>
                        @endif
                    </td>
                    <td>{{ $u->last_login_at?->format('Y-m-d H:i') ?? 'لم يدخل بعد' }}</td>
                    <td class="text-center" style="white-space:nowrap;">
                        <button type="button" class="btn btn-info btn-sm"
                                data-json="{{ json_encode($userData, JSON_UNESCAPED_UNICODE) }}"
                                onclick="openEditUser(this)">تعديل</button>
                        <button type="button" class="btn btn-warning btn-sm"
                                data-id="{{ $u->id }}" data-username="{{ $u->username }}"
                                onclick="openResetPw(this)">كلمة مرور</button>
                        @if($u->id !== auth()->id())
                            <form method="POST" action="{{ route('users.toggle_active', $u) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">{{ $u->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                            </form>
                            <form method="POST" action="{{ route('users.destroy', $u) }}" style="display:inline;" onsubmit="return confirm('نقل المستخدم «{{ $u->username }}» إلى سلة المحذوفات؟');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal-backdrop" id="createUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">إنشاء حساب مستخدم جديد</h3>
            <button type="button" class="modal-close" onclick="closeModal('createUserModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">اسم المستخدم (إنجليزي بدون مسافات) *</label>
                        <input type="text" name="username" class="form-control" required placeholder="مثال: water_dept">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم الكامل *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الصلاحية *</label>
                        <select name="role" class="form-select" required>
                            <option value="department">مستخدم دائرة</option>
                            <option value="admin">مشرف عام</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الدائرة (لمستخدم الدائرة)</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- اختر الدائرة --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">كلمة المرور الأولية *</label>
                        <input type="text" name="password" class="form-control" required value="Dept@1234">
                    </div>
                    <div class="form-group form-full">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:700;">
                            <input type="checkbox" name="must_change_password" value="1" checked>
                            <span>إلزام المستخدم بتغيير كلمة المرور بمجرد الدخول لأول مرة</span>
                        </label>
                    </div>
                </div>
                <div class="actions-row" style="margin-top:12px;">
                    <button type="submit" class="btn btn-success">إنشاء الحساب</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('createUserModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-backdrop" id="editUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">تعديل بيانات المستخدم</h3>
            <button type="button" class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editUserForm">
                @csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">اسم المستخدم *</label>
                        <input type="text" name="username" id="eu_username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم الكامل *</label>
                        <input type="text" name="name" id="eu_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الصلاحية *</label>
                        <select name="role" id="eu_role" class="form-select">
                            <option value="department">مستخدم دائرة</option>
                            <option value="admin">مشرف عام</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الدائرة</label>
                        <select name="department_id" id="eu_dept" class="form-select">
                            <option value="">-- بدون دائرة --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" id="eu_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="is_active" id="eu_active" value="1"> الحساب نشط</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="must_change_password" id="eu_must" value="1"> إلزام بتغيير كلمة المرور</label>
                    </div>
                </div>
                <div class="actions-row" style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-backdrop" id="resetPwModal">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3 class="modal-title">إعادة تعيين كلمة مرور: <span id="rpw_user"></span></h3>
            <button type="button" class="modal-close" onclick="closeModal('resetPwModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="resetPwForm">
                @csrf
                <div class="form-group">
                    <label class="form-label">كلمة المرور الجديدة (8 أحرف + حروف وأرقام)</label>
                    <input type="text" name="new_password" class="form-control" required value="Dept@2027">
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="force_change" value="1" checked> إلزام المستخدم بتغييرها عند الدخول التالي</label>
                </div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-warning">تعيين كلمة المرور</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('resetPwModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openEditUser(btn) {
    const u = parseBtnData(btn);
    if (!u) return;
    document.getElementById('editUserForm').action = `/users/${u.id}`;
    document.getElementById('eu_username').value = u.username || '';
    document.getElementById('eu_name').value = u.name || '';
    document.getElementById('eu_role').value = u.role || 'department';
    document.getElementById('eu_dept').value = u.department_id || '';
    document.getElementById('eu_email').value = u.email || '';
    document.getElementById('eu_active').checked = !!u.is_active;
    document.getElementById('eu_must').checked = !!u.must_change_password;
    openModal('editUserModal');
}
function openResetPw(btn) {
    document.getElementById('rpw_user').textContent = btn.dataset.username || '';
    document.getElementById('resetPwForm').action = `/users/${btn.dataset.id}/reset-password`;
    openModal('resetPwModal');
}
</script>
@endpush
