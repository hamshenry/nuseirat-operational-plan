/* بلدية النصيرات — نظام إدارة الخطة التشغيلية (Enhanced Bulletproof JS) */

function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    const firstInput = el.querySelector('input:not([type="hidden"]), select, textarea');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 60);
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

function closeAllModals() {
    document.querySelectorAll('.modal-backdrop.open').forEach(m => m.classList.remove('open'));
}

/* Safe JSON decoder from data-json attribute (never breaks on quotes/Arabic/newlines) */
function parseBtnData(btn, attr = 'json') {
    if (!btn || !btn.dataset || !btn.dataset[attr]) return null;
    try {
        return JSON.parse(btn.dataset[attr]);
    } catch (e) {
        console.error('JSON parse error on button data-' + attr, e);
        return null;
    }
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

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeAllModals();
        const notifDrop = document.getElementById('notifDropdown');
        if (notifDrop) notifDrop.classList.remove('open');
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

/* Bulk Selection for Batch Approval / Rejection / Submission (with Modal instead of prompt!) */
function initBulkSelection() {
    const master = document.getElementById('selectAllActivities');
    const bar = document.getElementById('bulkActionBar');
    const countEl = document.getElementById('bulkSelectedCount');
    if (!master || !bar) return;

    const updateBar = () => {
        const checked = document.querySelectorAll('.activity-checkbox:checked');
        if (countEl) countEl.textContent = checked.length;
        bar.classList.toggle('visible', checked.length > 0);
        const all = document.querySelectorAll('.activity-checkbox');
        master.checked = all.length > 0 && checked.length === all.length;
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

function openBatchModal(action) {
    const checked = document.querySelectorAll('.activity-checkbox:checked');
    if (!checked.length) {
        alert('يرجى تحديد نشاط واحد على الأقل.');
        return;
    }

    const actionInput = document.getElementById('batchActionType');
    const noteInput = document.getElementById('batchActionNote');
    const form = document.getElementById('batchActionForm');

    if (action === 'submit') {
        if (!confirm(`إرسال ${checked.length} نشاط محدد للمشرف العام للمراجعة والاعتماد؟`)) return;
        actionInput.value = 'submit';
        noteInput.value = '';
        form.submit();
        return;
    }
    if (action === 'delete') {
        if (!confirm(`هل أنت متأكد من نقل ${checked.length} نشاط إلى سلة المحذوفات؟`)) return;
        actionInput.value = 'delete';
        noteInput.value = '';
        form.submit();
        return;
    }

    // Open rich modal for batch approve / batch reject
    const modal = document.getElementById('batchDecisionModal');
    if (!modal) return;
    const titleEl = document.getElementById('batchModalTitle');
    const labelEl = document.getElementById('batchModalLabel');
    const textarea = document.getElementById('batchModalTextarea');
    const submitBtn = document.getElementById('batchModalConfirmBtn');

    modal.dataset.pendingAction = action;
    if (action === 'approve') {
        titleEl.textContent = `✅ اعتماد جماعي لعدد (${checked.length}) نشاط`;
        labelEl.textContent = 'ملاحظة الاعتماد الجماعي (اختياري)';
        textarea.required = false;
        textarea.value = 'تمت المراجعة والاعتماد دفعة واحدة.';
        submitBtn.className = 'btn btn-success';
        submitBtn.textContent = `تأكيد اعتماد ${checked.length} نشاط`;
    } else {
        titleEl.textContent = `❌ رفض جماعي لعدد (${checked.length}) نشاط مع بيان السبب`;
        labelEl.textContent = 'سبب الرفض والتعديلات المطلوبة (إلزامي — 5 أحرف على الأقل) *';
        textarea.required = true;
        textarea.value = '';
        textarea.placeholder = 'اكتب سبب الرفض بوضوح ليتم إشعار الدوائر...';
        submitBtn.className = 'btn btn-danger';
        submitBtn.textContent = `تأكيد رفض ${checked.length} نشاط`;
    }
    openModal('batchDecisionModal');
}

function confirmBatchModal() {
    const modal = document.getElementById('batchDecisionModal');
    const action = modal?.dataset.pendingAction;
    const textarea = document.getElementById('batchModalTextarea');
    const val = (textarea?.value || '').trim();

    if (action === 'reject' && val.length < 5) {
        alert('يرجى كتابة سبب واضح للرفض (5 أحرف على الأقل).');
        textarea?.focus();
        return;
    }

    document.getElementById('batchActionType').value = action;
    document.getElementById('batchActionNote').value = val;
    closeModal('batchDecisionModal');
    document.getElementById('batchActionForm').submit();
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
