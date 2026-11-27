@extends('layouts.app')
@section('title', 'إدارة الدوائر البلدية')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">إدارة الدوائر البلدية</h2>
        <p class="page-subtitle">إضافة الدوائر وإنشاء اسم المستخدم وكلمة المرور الأولية لكل دائرة وإدارة استثناءات فترات الإدخال</p>
    </div>
    <button type="button" class="btn btn-success" onclick="openModal('createDeptModal')">➕ إضافة دائرة جديدة</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>الرمز</th>
                <th>اسم الدائرة</th>
                <th>المدير / المسؤول</th>
                <th>حسابات الدخول</th>
                <th class="text-center">الأنشطة</th>
                <th class="text-center">حالة فترة الإدخال للدائرة</th>
                <th class="text-center">الإجراءات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($departments as $dept)
                <tr>
                    <td class="fw-bold"><code>{{ $dept->code }}</code></td>
                    <td>
                        <div class="fw-bold">{{ $dept->name }}</div>
                        <div class="text-muted" style="font-size:12px;">{{ $dept->description }}</div>
                    </td>
                    <td>
                        <div>{{ $dept->manager_name ?: '—' }}</div>
                        <div class="text-muted" style="font-size:12px;">{{ $dept->phone }} {{ $dept->email ? ' | ' . $dept->email : '' }}</div>
                    </td>
                    <td>
                        @forelse($dept->users as $u)
                            <div style="margin-bottom:4px;">
                                <code>{{ $u->username }}</code> ({{ $u->name }})
                                @if($u->must_change_password)
                                    <span class="badge badge-warning">ينتظر تغيير كلمة المرور</span>
                                @endif
                            </div>
                        @empty
                            <span class="text-muted">لا يوجد حساب بعد</span>
                        @endforelse
                    </td>
                    <td class="text-center"><span class="badge badge-info">{{ $dept->activities_count }}</span></td>
                    <td class="text-center">
                        @if($dept->entry_override_open === true)
                            <span class="badge badge-success">مفتوح استثنائياً {{ $dept->entry_override_until ? 'حتى ' . $dept->entry_override_until->format('Y-m-d') : '' }}</span>
                        @elseif($dept->entry_override_open === false)
                            <span class="badge badge-danger">مغلق استثنائياً</span>
                        @else
                            <span class="badge badge-secondary">يتبع إعداد السنة</span>
                        @endif
                    </td>
                    <td class="text-center" style="white-space:nowrap;">
                        <button type="button" class="btn btn-info btn-sm"
                                onclick='openEditDept(@json($dept))'>تعديل</button>
                        <form method="POST" action="{{ route('departments.destroy', $dept) }}" style="display:inline;" onsubmit="return confirm('نقل الدائرة «{{ $dept->name }}» إلى سلة المحذوفات؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">لا توجد دوائر مضافة بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Create Department + Initial User Account Modal -->
<div class="modal-backdrop" id="createDeptModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">إضافة دائرة جديدة وحساب الدخول</h3>
            <button type="button" class="modal-close" onclick="closeModal('createDeptModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('departments.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">رمز الدائرة *</label>
                        <input type="text" name="code" class="form-control" required placeholder="مثال: D06">
                    </div>
                    <div class="form-group">
                        <label class="form-label">اسم الدائرة *</label>
                        <input type="text" name="name" class="form-control" required placeholder="مثال: دائرة العلاقات العامة">
                    </div>
                    <div class="form-group">
                        <label class="form-label">مدير / رئيس الدائرة</label>
                        <input type="text" name="manager_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">وصف مهام الدائرة</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>
                </div>

                <div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:14px;border-radius:10px;margin:10px 0 16px;">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:800;color:#166534;margin-bottom:10px;">
                        <input type="checkbox" name="create_account" value="1" checked>
                        <span>إنشاء اسم مستخدم وكلمة مرور أولية للدائرة مباشرة (تتغير عند أول دخول)</span>
                    </label>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">اسم المستخدم للدائرة</label>
                            <input type="text" name="username" class="form-control" placeholder="مثال: pr_dept">
                        </div>
                        <div class="form-group">
                            <label class="form-label">كلمة المرور الأولية</label>
                            <input type="text" name="password" class="form-control" value="Dept@1234">
                        </div>
                        <div class="form-group form-full">
                            <label class="form-label">الاسم الظاهر للمستخدم</label>
                            <input type="text" name="account_full_name" class="form-control" placeholder="مثال: مدير العلاقات العامة">
                        </div>
                    </div>
                </div>

                <div class="actions-row">
                    <button type="submit" class="btn btn-success">حفظ الدائرة وإنشاء الحساب</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('createDeptModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal-backdrop" id="editDeptModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">تعديل بيانات الدائرة</h3>
            <button type="button" class="modal-close" onclick="closeModal('editDeptModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editDeptForm">
                @csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">رمز الدائرة *</label>
                        <input type="text" name="code" id="ed_code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">اسم الدائرة *</label>
                        <input type="text" name="name" id="ed_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">مدير الدائرة</label>
                        <input type="text" name="manager_name" id="ed_mgr" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الهاتف</label>
                        <input type="text" name="phone" id="ed_phone" class="form-control">
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" id="ed_email" class="form-control">
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" id="ed_desc" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">استثناء فترة إدخال الخطط للدائرة</label>
                        <select name="entry_override_mode" id="ed_override" class="form-select">
                            <option value="inherit">يتبع الإعداد العام للسنة</option>
                            <option value="open">فتح استثنائي لهذه الدائرة</option>
                            <option value="closed">إغلاق استثنائي لهذه الدائرة</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ انتهاء التمديد الاستثنائي</label>
                        <input type="date" name="entry_override_until" id="ed_until" class="form-control">
                    </div>
                </div>
                <div class="actions-row" style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('editDeptModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openEditDept(d) {
    document.getElementById('editDeptForm').action = `/departments/${d.id}`;
    document.getElementById('ed_code').value = d.code || '';
    document.getElementById('ed_name').value = d.name || '';
    document.getElementById('ed_mgr').value = d.manager_name || '';
    document.getElementById('ed_phone').value = d.phone || '';
    document.getElementById('ed_email').value = d.email || '';
    document.getElementById('ed_desc').value = d.description || '';
    document.getElementById('ed_override').value = d.entry_override_open === true ? 'open' : (d.entry_override_open === false ? 'closed' : 'inherit');
    document.getElementById('ed_until').value = d.entry_override_until ? String(d.entry_override_until).substring(0, 10) : '';
    openModal('editDeptModal');
}
</script>
@endpush
