@extends('layouts.app')
@section('title', 'إدارة الأهداف الرئيسية والفرعية والبرامج')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">🎯 الهيكل الاستراتيجي (الأهداف والبرامج) — عام {{ $year->year }}</h2>
        <p class="page-subtitle">إدخال وتعديل وحذف الأهداف الرئيسية وتحتها الأهداف الفرعية وتحتها البرامج التي تختار منها الدوائر</p>
    </div>
    <div class="actions-row">
        <input type="text" id="treeSearchInput" class="form-control" style="width:230px;" placeholder="🔍 تصفية الشجرة فورياً..." oninput="filterStrategicTree(this.value)">
        <button type="button" class="btn btn-success" onclick="openModal('addMainModal')">➕ إضافة هدف رئيسي جديد</button>
    </div>
</div>

@forelse($mainObjectives as $mo)
    @php
        $moData = ['id' => $mo->id, 'code' => $mo->code, 'title' => $mo->title, 'description' => $mo->description ?? ''];
    @endphp
    <div class="tree-main" data-tree-text="{{ mb_strtolower($mo->code . ' ' . $mo->title) }}">
        <div class="tree-main-head">
            <div>
                <span class="badge badge-primary" style="font-size:13px;">هدف رئيسي {{ $mo->code }}</span>
                <strong style="font-size:16px;margin-right:6px;color:var(--primary);">{{ $mo->title }}</strong>
            </div>
            <div class="actions-row">
                <button type="button" class="btn btn-success btn-sm"
                        data-id="{{ $mo->id }}" data-label="{{ $mo->code }} — {{ $mo->title }}"
                        onclick="openAddSub(this)">+ هدف فرعي</button>
                <button type="button" class="btn btn-outline btn-sm"
                        data-json="{{ json_encode($moData, JSON_UNESCAPED_UNICODE) }}"
                        onclick="openEditMain(this)">تعديل</button>
                <form method="POST" action="{{ route('objectives.main.destroy', $mo) }}" onsubmit="return confirm('نقل الهدف الرئيسي إلى سلة المحذوفات؟');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                </form>
            </div>
        </div>

        <div style="padding:4px 0 12px;">
            @forelse($mo->subObjectives as $so)
                @php
                    $soData = ['id' => $so->id, 'main_objective_id' => $so->main_objective_id, 'code' => $so->code, 'title' => $so->title, 'description' => $so->description ?? ''];
                @endphp
                <div class="tree-sub">
                    <div class="tree-sub-head">
                        <div>
                            <span class="badge badge-info">هدف فرعي {{ $so->code }}</span>
                            <strong style="margin-right:6px;">{{ $so->title }}</strong>
                        </div>
                        <div class="actions-row">
                            <button type="button" class="btn btn-primary btn-sm"
                                    data-id="{{ $so->id }}" data-label="{{ $so->code }} — {{ $so->title }}"
                                    onclick="openAddProg(this)">+ برنامج</button>
                            <button type="button" class="btn btn-outline btn-sm"
                                    data-json="{{ json_encode($soData, JSON_UNESCAPED_UNICODE) }}"
                                    onclick="openEditSub(this)">تعديل</button>
                            <form method="POST" action="{{ route('objectives.sub.destroy', $so) }}" onsubmit="return confirm('نقل الهدف الفرعي إلى سلة المحذوفات؟');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                            </form>
                        </div>
                    </div>

                    @forelse($so->programs as $pr)
                        @php
                            $prData = ['id' => $pr->id, 'sub_objective_id' => $pr->sub_objective_id, 'code' => $pr->code, 'title' => $pr->title, 'description' => $pr->description ?? ''];
                        @endphp
                        <div class="tree-prog">
                            <div>
                                <span class="badge badge-success">برنامج {{ $pr->code }}</span>
                                <span style="margin-right:6px;font-weight:600;">{{ $pr->title }}</span>
                            </div>
                            <div class="actions-row">
                                <button type="button" class="btn btn-outline btn-sm"
                                        data-json="{{ json_encode($prData, JSON_UNESCAPED_UNICODE) }}"
                                        onclick="openEditProg(this)">تعديل</button>
                                <form method="POST" action="{{ route('objectives.program.destroy', $pr) }}" onsubmit="return confirm('نقل البرنامج إلى سلة المحذوفات؟');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger btn-sm">حذف</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div style="padding:10px 16px;color:var(--text-soft);font-size:12.5px;">لا توجد برامج تحت هذا الهدف الفرعي بعد.</div>
                    @endforelse
                </div>
            @empty
                <div style="padding:14px 18px;color:var(--text-soft);font-size:13px;">لا توجد أهداف فرعية تحت هذا الهدف الرئيسي بعد.</div>
            @endforelse
        </div>
    </div>
@empty
    <div class="card"><div class="card-body text-center text-muted" style="padding:40px;">لم يتم إدخال أهداف رئيسية لعام {{ $year->year }} بعد. ابدأ بإضافة هدف رئيسي أو انسخ هيكل سنة سابقة.</div></div>
@endforelse

<!-- Add Main Objective Modal -->
<div class="modal-backdrop" id="addMainModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header"><h3 class="modal-title">إضافة هدف رئيسي جديد ({{ $year->year }})</h3><button type="button" class="modal-close" onclick="closeModal('addMainModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" action="{{ route('objectives.main.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">الرمز (اختياري — يُولّد تلقائياً إذا تُرك فارغاً)</label>
                    <input type="text" name="code" class="form-control" placeholder="مثال: 4">
                </div>
                <div class="form-group">
                    <label class="form-label">عنوان الهدف الرئيسي *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">وصف إضافي</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-success">حفظ الهدف الرئيسي</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('addMainModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Main Modal -->
<div class="modal-backdrop" id="editMainModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header"><h3 class="modal-title">تعديل الهدف الرئيسي</h3><button type="button" class="modal-close" onclick="closeModal('editMainModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" id="editMainForm">
                @csrf @method('PUT')
                <div class="form-group"><label class="form-label">الرمز *</label><input type="text" name="code" id="em_code" class="form-control" required></div>
                <div class="form-group"><label class="form-label">العنوان *</label><input type="text" name="title" id="em_title" class="form-control" required></div>
                <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" id="em_desc" class="form-control"></textarea></div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-primary">حفظ التعديل</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('editMainModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Sub Objective Modal -->
<div class="modal-backdrop" id="addSubModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header"><h3 class="modal-title">إضافة هدف فرعي</h3><button type="button" class="modal-close" onclick="closeModal('addSubModal')">&times;</button></div>
        <div class="modal-body">
            <p class="text-muted" id="subParentLabel" style="margin-top:0;font-weight:700;"></p>
            <form method="POST" action="{{ route('objectives.sub.store') }}">
                @csrf
                <input type="hidden" name="main_objective_id" id="sub_main_id">
                <div class="form-group"><label class="form-label">الرمز (يُولّد تلقائياً إذا تُرك فارغاً)</label><input type="text" name="code" class="form-control" placeholder="مثال: 1.3"></div>
                <div class="form-group"><label class="form-label">عنوان الهدف الفرعي *</label><input type="text" name="title" class="form-control" required></div>
                <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" class="form-control"></textarea></div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-success">حفظ الهدف الفرعي</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('addSubModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Sub Modal -->
<div class="modal-backdrop" id="editSubModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header"><h3 class="modal-title">تعديل الهدف الفرعي</h3><button type="button" class="modal-close" onclick="closeModal('editSubModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" id="editSubForm">
                @csrf @method('PUT')
                <input type="hidden" name="main_objective_id" id="es_main_id">
                <div class="form-group"><label class="form-label">الرمز *</label><input type="text" name="code" id="es_code" class="form-control" required></div>
                <div class="form-group"><label class="form-label">العنوان *</label><input type="text" name="title" id="es_title" class="form-control" required></div>
                <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" id="es_desc" class="form-control"></textarea></div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-primary">حفظ التعديل</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('editSubModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Program Modal -->
<div class="modal-backdrop" id="addProgModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header"><h3 class="modal-title">إضافة برنامج جديد</h3><button type="button" class="modal-close" onclick="closeModal('addProgModal')">&times;</button></div>
        <div class="modal-body">
            <p class="text-muted" id="progParentLabel" style="margin-top:0;font-weight:700;"></p>
            <form method="POST" action="{{ route('objectives.program.store') }}">
                @csrf
                <input type="hidden" name="sub_objective_id" id="prog_sub_id">
                <div class="form-group"><label class="form-label">الرمز (يُولّد تلقائياً إذا تُرك فارغاً)</label><input type="text" name="code" class="form-control" placeholder="مثال: 1.1.3"></div>
                <div class="form-group"><label class="form-label">اسم البرنامج *</label><input type="text" name="title" class="form-control" required></div>
                <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" class="form-control"></textarea></div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-success">حفظ البرنامج</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('addProgModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal-backdrop" id="editProgModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header"><h3 class="modal-title">تعديل البرنامج</h3><button type="button" class="modal-close" onclick="closeModal('editProgModal')">&times;</button></div>
        <div class="modal-body">
            <form method="POST" id="editProgForm">
                @csrf @method('PUT')
                <input type="hidden" name="sub_objective_id" id="ep_sub_id">
                <div class="form-group"><label class="form-label">الرمز *</label><input type="text" name="code" id="ep_code" class="form-control" required></div>
                <div class="form-group"><label class="form-label">اسم البرنامج *</label><input type="text" name="title" id="ep_title" class="form-control" required></div>
                <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" id="ep_desc" class="form-control"></textarea></div>
                <div class="actions-row">
                    <button type="submit" class="btn btn-primary">حفظ التعديل</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('editProgModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openEditMain(btn) {
    const m = parseBtnData(btn);
    if (!m) return;
    document.getElementById('editMainForm').action = `/objectives/main/${m.id}`;
    document.getElementById('em_code').value = m.code || '';
    document.getElementById('em_title').value = m.title || '';
    document.getElementById('em_desc').value = m.description || '';
    openModal('editMainModal');
}
function openAddSub(btn) {
    document.getElementById('sub_main_id').value = btn.dataset.id;
    document.getElementById('subParentLabel').textContent = 'تحت الهدف الرئيسي: ' + (btn.dataset.label || '');
    openModal('addSubModal');
}
function openEditSub(btn) {
    const s = parseBtnData(btn);
    if (!s) return;
    document.getElementById('editSubForm').action = `/objectives/sub/${s.id}`;
    document.getElementById('es_main_id').value = s.main_objective_id;
    document.getElementById('es_code').value = s.code || '';
    document.getElementById('es_title').value = s.title || '';
    document.getElementById('es_desc').value = s.description || '';
    openModal('editSubModal');
}
function openAddProg(btn) {
    document.getElementById('prog_sub_id').value = btn.dataset.id;
    document.getElementById('progParentLabel').textContent = 'تحت الهدف الفرعي: ' + (btn.dataset.label || '');
    openModal('addProgModal');
}
function openEditProg(btn) {
    const p = parseBtnData(btn);
    if (!p) return;
    document.getElementById('editProgForm').action = `/objectives/program/${p.id}`;
    document.getElementById('ep_sub_id').value = p.sub_objective_id;
    document.getElementById('ep_code').value = p.code || '';
    document.getElementById('ep_title').value = p.title || '';
    document.getElementById('ep_desc').value = p.description || '';
    openModal('editProgModal');
}
function filterStrategicTree(q) {
    const kw = (q || '').trim().toLowerCase();
    document.querySelectorAll('.tree-main').forEach(box => {
        box.style.display = (!kw || box.textContent.toLowerCase().includes(kw)) ? '' : 'none';
    });
}
</script>
@endpush
