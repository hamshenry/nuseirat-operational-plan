@extends('layouts.app')
@section('title', 'إدارة السنوات وفترات الإدخال ونسخ الخطط')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">📆 إدارة السنوات وفتح/إغلاق فترات الإدخال ونسخ الخطط</h2>
        <p class="page-subtitle">التحكم بالسنوات التخطيطية، تفعيل السنة الحالية، فتح أو إغلاق نافذة إدخال الخطط، ونسخ خطة سنة سابقة لسنة جديدة</p>
    </div>
    <div class="actions-row">
        <button type="button" class="btn btn-success" onclick="openModal('createYearModal')">➕ إضافة سنة تخطيطية</button>
        <button type="button" class="btn btn-primary" onclick="openModal('cloneYearModal')">📋 نسخ خطة سنة سابقة</button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>السنة</th>
                <th>عنوان الخطة</th>
                <th class="text-center">الحالة</th>
                <th class="text-center">فترة إدخال الخطط</th>
                <th class="text-center">الأهداف / الأنشطة</th>
                <th class="text-center">الإجراءات</th>
            </tr>
            </thead>
            <tbody>
            @foreach($years as $y)
                <tr>
                    <td class="fw-bold" style="font-size:17px;color:var(--primary);">{{ $y->year }}</td>
                    <td>
                        <div class="fw-bold">{{ $y->title }}</div>
                        <div class="text-muted" style="font-size:12px;">{{ $y->notes }}</div>
                    </td>
                    <td class="text-center">
                        @if($y->is_active)
                            <span class="badge badge-success">السنة النشطة</span>
                        @else
                            <form method="POST" action="{{ route('years.activate', $y) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">تعيين كنشطة</button>
                            </form>
                        @endif
                    </td>
                    <td class="text-center">
                        <div>
                            <span class="badge {{ $y->is_entry_open ? 'badge-success' : 'badge-danger' }}">
                                {{ $y->is_entry_open ? '🔓 مفتوحة للإدخال' : '🔒 مغلقة' }}
                            </span>
                        </div>
                        <div class="text-muted" style="font-size:11.5px;margin-top:3px;">
                            {{ $y->entry_start_date?->format('Y-m-d') ?? '—' }} ← {{ $y->entry_end_date?->format('Y-m-d') ?? '—' }}
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-info">{{ $y->main_objectives_count }} أهداف</span>
                        <span class="badge badge-secondary">{{ $y->activities_count }} نشاط</span>
                    </td>
                    <td class="text-center" style="white-space:nowrap;">
                        <form method="POST" action="{{ route('years.toggle_entry', $y) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn {{ $y->is_entry_open ? 'btn-warning' : 'btn-success' }} btn-sm">
                                {{ $y->is_entry_open ? '🔒 إغلاق الفترة' : '🔓 فتح الفترة' }}
                            </button>
                        </form>
                        <button type="button" class="btn btn-info btn-sm" onclick='openEditYear(@json($y))'>تعديل التواريخ</button>
                        @if(!$y->is_active)
                            <form method="POST" action="{{ route('years.destroy', $y) }}" style="display:inline;" onsubmit="return confirm('نقل السنة {{ $y->year }} إلى سلة المحذوفات؟');">
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

<!-- Create Year Modal -->
<div class="modal-backdrop" id="createYearModal">
    <div class="modal-box">
        <div class="modal-header"><h3 class="modal-title">إضافة سنة تخطيطية جديدة</h3><button class="modal-close" onclick="closeModal('createYearModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" action="{{ route('years.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">السنة (مثال: 2028) *</label><input type="number" name="year" class="form-control" required min="2020" max="2100" value="{{ now()->year + 1 }}"></div>
                    <div class="form-group"><label class="form-label">عنوان الخطة</label><input type="text" name="title" class="form-control" placeholder="الخطة التشغيلية لبلدية النصيرات..."></div>
                    <div class="form-group"><label class="form-label">تاريخ بداية فترة الإدخال</label><input type="date" name="entry_start_date" class="form-control"></div>
                    <div class="form-group"><label class="form-label">تاريخ نهاية فترة الإدخال</label><input type="date" name="entry_end_date" class="form-control"></div>
                    <div class="form-group form-full"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control"></textarea></div>
                    <div class="form-group"><label><input type="checkbox" name="is_entry_open" value="1" checked> فتح فترة الإدخال مباشرة</label></div>
                    <div class="form-group"><label><input type="checkbox" name="set_active" value="1"> تعيينها كالسنة النشطة الحالية</label></div>
                </div>
                <button type="submit" class="btn btn-success">إنشاء السنة</button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Year Modal -->
<div class="modal-backdrop" id="editYearModal">
    <div class="modal-box">
        <div class="modal-header"><h3 class="modal-title">تعديل إعدادات وتواريخ السنة التخطيطية</h3><button class="modal-close" onclick="closeModal('editYearModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" id="editYearForm">
                @csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group form-full"><label class="form-label">العنوان *</label><input type="text" name="title" id="ey_title" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">بداية فترة الإدخال</label><input type="date" name="entry_start_date" id="ey_start" class="form-control"></div>
                    <div class="form-group"><label class="form-label">نهاية فترة الإدخال</label><input type="date" name="entry_end_date" id="ey_end" class="form-control"></div>
                    <div class="form-group form-full"><label class="form-label">ملاحظات</label><textarea name="notes" id="ey_notes" class="form-control"></textarea></div>
                    <div class="form-group form-full"><label><input type="checkbox" name="is_entry_open" id="ey_open" value="1"> فترة إدخال الخطط مفتوحة</label></div>
                </div>
                <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
            </form>
        </div>
    </div>
</div>

<!-- Clone Previous Year Plan Modal -->
<div class="modal-backdrop" id="cloneYearModal">
    <div class="modal-box">
        <div class="modal-header"><h3 class="modal-title">📋 نسخ خطة سنة سابقة إلى سنة جديدة</h3><button class="modal-close" onclick="closeModal('cloneYearModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" action="{{ route('years.clone') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">السنة المصدر (النسخ منها) *</label>
                        <select name="source_year_id" class="form-select" required>
                            @foreach($years as $y)
                                <option value="{{ $y->id }}">{{ $y->year }} ({{ $y->activities_count }} نشاط)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">السنة الهدف (النسخ إليها) *</label>
                        <select name="target_year_id" class="form-select" required>
                            @foreach($years as $y)
                                <option value="{{ $y->id }}">{{ $y->year }} — {{ $y->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label class="form-label">تحديد دائرة معينة (اختياري — اتركه فارغاً لنسخ كافة الدوائر)</label>
                        <select name="department_id" class="form-select">
                            <option value="">كافة الدوائر</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="clone_activities" value="1" checked> نسخ الأنشطة التشغيلية للدوائر (تُنسخ كمسودات مع ترقيم تلقائي جديد)</label>
                        <label style="display:block;"><input type="checkbox" name="clone_budgets" value="1" checked> نسخ مخصصات الموازنات المعتمدة للدوائر</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">🚀 تنفيذ النسخ الآن</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openEditYear(y) {
    document.getElementById('editYearForm').action = `/years/${y.id}`;
    document.getElementById('ey_title').value = y.title || '';
    document.getElementById('ey_start').value = y.entry_start_date ? String(y.entry_start_date).substring(0, 10) : '';
    document.getElementById('ey_end').value = y.entry_end_date ? String(y.entry_end_date).substring(0, 10) : '';
    document.getElementById('ey_notes').value = y.notes || '';
    document.getElementById('ey_open').checked = !!y.is_entry_open;
    openModal('editYearModal');
}
</script>
@endpush
