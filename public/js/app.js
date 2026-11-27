/* بلدية النصيرات — نظام إدارة الخطة التشغيلية */

function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('open');
    }
    const notifWrap = document.querySelector('.notif-wrapper');
    const notifDrop = document.getElementById('notifDropdown');
    if (notifDrop && notifWrap && !notifWrap.contains(e.target)) {
        notifDrop.classList.remove('open');
    }
});

function toggleNotifications() {
    const el = document.getElementById('notifDropdown');
    if (el) el.classList.toggle('open');
}

function toggleMobileSidebar() {
    const sb = document.getElementById('appSidebar');
    if (sb) sb.classList.toggle('mobile-open');
}

/* Cascading Hierarchy Dropdowns: Main Objective -> Sub Objective -> Program */
async function loadSubObjectives(mainId, subSelectId, progSelectId, selectedSubId = null, selectedProgId = null) {
    const subSel = document.getElementById(subSelectId);
    const progSel = document.getElementById(progSelectId);
    if (!subSel) return;

    const defaultSubText = subSel.dataset.placeholder || '-- اختر الهدف الفرعي --';
    const defaultProgText = progSel ? (progSel.dataset.placeholder || '-- اختر البرنامج --') : '';

    subSel.innerHTML = `<option value="">${defaultSubText}</option>`;
    if (progSel) progSel.innerHTML = `<option value="">${defaultProgText}</option>`;

    if (!mainId) return;

    try {
        const res = await fetch(`/api/hierarchy/sub-objectives/${encodeURIComponent(mainId)}`, {
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) return;
        const items = await res.json();
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = `${item.code} — ${item.title}`;
            if (selectedSubId && String(selectedSubId) === String(item.id)) {
                opt.selected = true;
            }
            subSel.appendChild(opt);
        });

        if (selectedSubId && progSelectId) {
            await loadPrograms(selectedSubId, progSelectId, selectedProgId);
        }
    } catch (err) {
        console.error('Failed to load sub objectives', err);
    }
}

async function loadPrograms(subId, progSelectId, selectedProgId = null) {
    const progSel = document.getElementById(progSelectId);
    if (!progSel) return;

    const defaultProgText = progSel.dataset.placeholder || '-- اختر البرنامج --';
    progSel.innerHTML = `<option value="">${defaultProgText}</option>`;
    if (!subId) return;

    try {
        const res = await fetch(`/api/hierarchy/programs/${encodeURIComponent(subId)}`, {
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) return;
        const items = await res.json();
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = `${item.code} — ${item.title}`;
            if (selectedProgId && String(selectedProgId) === String(item.id)) {
                opt.selected = true;
            }
            progSel.appendChild(opt);
        });
    } catch (err) {
        console.error('Failed to load programs', err);
    }
}

/* Bulk Selection for Batch Approval / Rejection / Submission */
function initBulkSelection() {
    const master = document.getElementById('selectAllActivities');
    const bar = document.getElementById('bulkActionBar');
    const countEl = document.getElementById('bulkSelectedCount');
    if (!master || !bar) return;

    const updateBar = () => {
        const checked = document.querySelectorAll('.activity-checkbox:checked');
        if (countEl) countEl.textContent = checked.length;
        bar.classList.toggle('visible', checked.length > 0);
    };

    master.addEventListener('change', () => {
        document.querySelectorAll('.activity-checkbox').forEach(cb => {
            cb.checked = master.checked;
        });
        updateBar();
    });

    document.querySelectorAll('.activity-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBar);
    });
}

function submitBatchForm(action) {
    const form = document.getElementById('batchActionForm');
    if (!form) return;
    const checked = document.querySelectorAll('.activity-checkbox:checked');
    if (!checked.length) {
        alert('يرجى تحديد نشاط واحد على الأقل.');
        return;
    }

    const actionInput = document.getElementById('batchActionType');
    const noteInput = document.getElementById('batchActionNote');

    if (action === 'reject') {
        const reason = prompt('يرجى إدخال سبب رفض الأنشطة المحددة (إلزامي):');
        if (!reason || reason.trim().length < 5) {
            alert('يجب إدخال سبب واضح لا يقل عن 5 أحرف.');
            return;
        }
        noteInput.value = reason.trim();
    } else if (action === 'approve') {
        const note = prompt('ملاحظة اعتماد جماعي (اختياري):', 'اعتماد جماعي من قبل المشرف العام.');
        if (note === null) return;
        noteInput.value = note.trim();
    } else if (action === 'delete') {
        if (!confirm(`هل أنت متأكد من نقل ${checked.length} نشاط إلى سلة المحذوفات؟`)) return;
    }

    actionInput.value = action;
    form.submit();
}

function toggleSupportFields() {
    const sel = document.getElementById('support_type');
    if (!sel) return;
    const intBox = document.getElementById('supportInternalBox');
    const extBox = document.getElementById('supportExternalBox');
    const v = sel.value;
    if (intBox) intBox.style.display = (v === 'internal' || v === 'both') ? 'block' : 'none';
    if (extBox) extBox.style.display = (v === 'external' || v === 'both') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    initBulkSelection();
    toggleSupportFields();
});
