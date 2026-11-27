@extends('layouts.app')
@section('title', $activity ? 'تعديل النشاط ' . $activity->activity_code : 'إدخال نشاط جديد')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">{{ $activity ? "تعديل النشاط ({$activity->activity_code})" : "إدخال نشاط جديد في الخطة التشغيلية — {$year->year}" }}</h2>
        <p class="page-subtitle">اختر الهدف الرئيسي ثم الهدف الفرعي ثم البرنامج، وأدخل كافة تفاصيل النشاط ومؤشر الأداء والتكلفة</p>
    </div>
    <a href="{{ route('activities.index') }}" class="btn btn-outline">↩ العودة لقائمة الأنشطة</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $activity ? route('activities.update', $activity) : route('activities.store') }}">
            @csrf
            @if($activity) @method('PUT') @endif

            <div class="form-grid">
                @if(auth()->user()->isAdmin() && !$activity)
                    <div class="form-group form-full">
                        <label class="form-label">الدائرة المنفذة <span style="color:#dc2626;">*</span></label>
                        <select name="department_id" class="form-select" required>
                            <option value="">-- اختر الدائرة --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" @selected(old('department_id', $selectedDepartmentId) == $d->id)>{{ $d->code }} — {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <!-- Step 1: Cascading Strategic Hierarchy -->
                <div class="form-group form-full" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:11px;padding:14px;">
                    <div style="font-weight:800;color:#0f3644;margin-bottom:10px;">🎯 التسلسل الاستراتيجي (الهدف الرئيسي ← الهدف الفرعي ← البرنامج)</div>
                    <div class="form-grid-3">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">1. الهدف الرئيسي <span style="color:#dc2626;">*</span></label>
                            <select name="main_objective_id" id="main_objective_id" class="form-select" required
                                    onchange="loadSubObjectives(this.value, 'sub_objective_id', 'program_id')">
                                <option value="">-- اختر الهدف الرئيسي --</option>
                                @foreach($mainObjectives as $mo)
                                    <option value="{{ $mo->id }}" @selected(old('main_objective_id', $activity?->main_objective_id) == $mo->id)>
                                        {{ $mo->code }} — {{ $mo->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">2. الهدف الفرعي <span style="color:#dc2626;">*</span></label>
                            <select name="sub_objective_id" id="sub_objective_id" class="form-select" required
                                    data-placeholder="-- اختر الهدف الفرعي --"
                                    onchange="loadPrograms(this.value, 'program_id')">
                                <option value="">-- اختر الهدف الفرعي --</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">3. البرنامج <span style="color:#dc2626;">*</span></label>
                            <select name="program_id" id="program_id" class="form-select" required
                                    data-placeholder="-- اختر البرنامج --">
                                <option value="">-- اختر البرنامج --</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Activity Details -->
                <div class="form-group form-full">
                    <label class="form-label">النشاط <span style="color:#dc2626;">*</span></label>
                    <textarea name="title" class="form-control" required placeholder="اكتب وصف النشاط التشغيلي بشكل واضح ومحدد...">{{ old('title', $activity?->title) }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">مؤشر الأداء <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="kpi_name" class="form-control" required value="{{ old('kpi_name', $activity?->kpi_name) }}" placeholder="مثال: عدد الشوارع المؤهلة أو نسبة الإنجاز">
                </div>

                <div class="form-group">
                    <label class="form-label">قيمة المؤشر المستهدفة <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="kpi_target" class="form-control" required value="{{ old('kpi_target', $activity?->kpi_target) }}" placeholder="مثال: 100% أو 12 تقرير أو 4.5 كم">
                </div>

                <div class="form-group form-full">
                    <label class="form-label">وسائل التحقق <span style="color:#dc2626;">*</span></label>
                    <textarea name="verification_means" class="form-control" required placeholder="مثال: محاضر الاستلام، تقارير الإنجاز، صور ميدانية، كشوفات الحضور...">{{ old('verification_means', $activity?->verification_means) }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">الجهة المسئولة <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="responsible_entity" class="form-control" required value="{{ old('responsible_entity', $activity?->responsible_entity) }}" placeholder="القسم أو الدائرة المسئولة مباشرة عن التنفيذ">
                </div>

                <div class="form-group">
                    <label class="form-label">نوع الجهة المساندة <span style="color:#dc2626;">*</span></label>
                    <select name="support_type" id="support_type" class="form-select" required onchange="toggleSupportFields()">
                        @php $st = old('support_type', $activity?->support_type ?? 'internal'); @endphp
                        <option value="internal" @selected($st === 'internal')>داخلي فقط</option>
                        <option value="external" @selected($st === 'external')>خارجي فقط</option>
                        <option value="both" @selected($st === 'both')>داخلي وخارجي معاً</option>
                        <option value="none" @selected($st === 'none')>لا يوجد جهة مساندة</option>
                    </select>
                </div>

                <div class="form-group" id="supportInternalBox">
                    <label class="form-label">الجهة المساندة (داخلي — داخل البلدية)</label>
                    <input type="text" name="support_internal" class="form-control" value="{{ old('support_internal', $activity?->support_internal) }}" placeholder="مثال: الدائرة المالية، تكنولوجيا المعلومات">
                </div>

                <div class="form-group" id="supportExternalBox">
                    <label class="form-label">الجهة المساندة (خارجي — شركاء ومؤسسات)</label>
                    <input type="text" name="support_external" class="form-control" value="{{ old('support_external', $activity?->support_external) }}" placeholder="مثال: وزارة الحكم المحلي، MDLF، سلطة المياه">
                </div>

                <!-- Quarters -->
                <div class="form-group form-full">
                    <label class="form-label">الأرباع التنفيذية (ر1، ر2، ر3، ر4)</label>
                    <div class="quarter-checks">
                        <label class="quarter-box">
                            <input type="checkbox" name="q1" value="1" @checked(old('q1', $activity?->q1))>
                            <span>الربع الأول (ر1)</span>
                        </label>
                        <label class="quarter-box">
                            <input type="checkbox" name="q2" value="1" @checked(old('q2', $activity?->q2))>
                            <span>الربع الثاني (ر2)</span>
                        </label>
                        <label class="quarter-box">
                            <input type="checkbox" name="q3" value="1" @checked(old('q3', $activity?->q3))>
                            <span>الربع الثالث (ر3)</span>
                        </label>
                        <label class="quarter-box">
                            <input type="checkbox" name="q4" value="1" @checked(old('q4', $activity?->q4))>
                            <span>الربع الرابع (ر4)</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">الأهمية / الأولوية <span style="color:#dc2626;">*</span></label>
                    @php $pr = old('priority', $activity?->priority ?? 'medium'); @endphp
                    <select name="priority" class="form-select" required>
                        <option value="critical" @selected($pr === 'critical')>عاجل ومهم</option>
                        <option value="high" @selected($pr === 'high')>مرتفعة</option>
                        <option value="medium" @selected($pr === 'medium')>متوسطة</option>
                        <option value="low" @selected($pr === 'low')>منخفضة</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">التكلفة التقديرية (شيكل) <span style="color:#dc2626;">*</span></label>
                    <input type="number" step="0.01" min="0" name="estimated_cost" class="form-control" required value="{{ old('estimated_cost', $activity?->estimated_cost ?? 0) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">مصدر التمويل</label>
                    @php $fs = old('funding_source', $activity?->funding_source ?? 'تمويل ذاتي'); @endphp
                    <select name="funding_source" class="form-select">
                        <option value="تمويل ذاتي" @selected($fs === 'تمويل ذاتي')>تمويل ذاتي</option>
                        <option value="منح ومشاريع" @selected($fs === 'منح ومشاريع')>منح ومشاريع</option>
                        <option value="تمويل خارجي" @selected($fs === 'تمويل خارجي')>تمويل خارجي</option>
                        <option value="تمويل مشترك" @selected($fs === 'تمويل مشترك')>تمويل مشترك</option>
                        <option value="حكومي" @selected($fs === 'حكومي')>حكومي</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">البند المالي (اختياري)</label>
                    <input type="text" name="budget_item" class="form-control" value="{{ old('budget_item', $activity?->budget_item) }}" placeholder="رقم أو اسم البند في الموازنة">
                </div>

                @if($activity)
                    <div class="form-group form-full">
                        <label class="form-label">ملخص سبب التعديل (يُحفظ في سجل الإصدارات)</label>
                        <input type="text" name="change_summary" class="form-control" placeholder="مثال: تحديث التكلفة ومؤشر الأداء وفق ملاحظة المشرف العام">
                    </div>
                @endif
            </div>

            <div class="actions-row" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);">
                <button type="submit" name="save_mode" value="draft" class="btn btn-primary btn-lg">
                    💾 حفظ كمسودة
                </button>
                <button type="submit" name="save_mode" value="submit" class="btn btn-success btn-lg">
                    📤 حفظ وإرسال للمشرف العام للاعتماد
                </button>
                <a href="{{ route('activities.index') }}" class="btn btn-outline">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mainVal = @json(old('main_objective_id', $activity?->main_objective_id));
    const subVal = @json(old('sub_objective_id', $activity?->sub_objective_id));
    const progVal = @json(old('program_id', $activity?->program_id));
    if (mainVal) {
        loadSubObjectives(mainVal, 'sub_objective_id', 'program_id', subVal, progVal);
    }
});
</script>
@endpush
