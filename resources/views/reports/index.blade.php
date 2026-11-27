@extends('layouts.app')
@section('title', 'التقارير والتصدير إلى Excel وPDF')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">📊 مركز التقارير والتصدير (Excel &amp; PDF) — عام {{ $year->year }}</h2>
        <p class="page-subtitle">تصدير كامل خطة بلدية النصيرات وفق النموذج الأصلي، وتصدير كافة التقارير التنفيذية والمالية بصيغتي Excel وPDF</p>
    </div>
</div>

@if(auth()->user()->isAdmin())
<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('reports.index') }}" class="actions-row">
            <label class="form-label" style="margin:0;">تصفية التقارير حسب الدائرة:</label>
            <select name="department_id" class="form-select" style="width:260px;" onchange="this.form.submit()">
                <option value="">كامل خطة البلدية (جميع الدوائر)</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->code }} — {{ $d->name }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>
@endif

@php $deptParam = request('department_id') ? ['department_id' => request('department_id')] : []; @endphp

<div class="grid-3">
    <div class="card" style="border-top:4px solid #0f4c5c;">
        <div class="card-body">
            <h3 class="card-title" style="margin-bottom:8px;">📘 كامل الخطة التشغيلية (النموذج الأصلي)</h3>
            <p class="text-muted" style="font-size:13px;min-height:58px;">
                يحتوي على شعار بلدية النصيرات وكافة الأعمدة الأصلية: الأهداف الرئيسية والفرعية والبرامج، الأنشطة، مؤشرات الأداء، وسائل التحقق، المسئولة، المساندة (داخلي/خارجي)، الأرباع ر1-ر4، الأهمية، والتكلفة + ورقة ملخص الدوائر.
            </p>
            <div class="actions-row">
                <a href="{{ route('reports.excel', array_merge(['type' => 'plan'], $deptParam)) }}" class="btn btn-success">📥 Excel النموذج الأصلي</a>
                <a href="{{ route('reports.pdf', array_merge(['type' => 'plan'], $deptParam)) }}" class="btn btn-danger">📄 PDF رسمي</a>
            </div>
        </div>
    </div>

    <div class="card" style="border-top:4px solid #059669;">
        <div class="card-body">
            <h3 class="card-title" style="margin-bottom:8px;">🏆 تقرير تقييم أداء الدوائر</h3>
            <p class="text-muted" style="font-size:13px;min-height:58px;">
                ترتيب الدوائر مع الدرجة النهائية من 100 وتفصيل معايير التقييم الخمسة (الإنجاز، الاعتماد، الالتزام، الموازنة، التوثيق).
            </p>
            <div class="actions-row">
                <a href="{{ route('reports.excel', array_merge(['type' => 'evaluation'], $deptParam)) }}" class="btn btn-success">📥 تصدير Excel</a>
                <a href="{{ route('reports.pdf', array_merge(['type' => 'evaluation'], $deptParam)) }}" class="btn btn-danger">📄 تصدير PDF</a>
            </div>
        </div>
    </div>

    <div class="card" style="border-top:4px solid #0284c7;">
        <div class="card-body">
            <h3 class="card-title" style="margin-bottom:8px;">📅 تقرير المتابعة الشهرية المتقدمة</h3>
            <p class="text-muted" style="font-size:13px;min-height:58px;">
                سجل شامل لجميع تقارير المتابعة الشهرية (المخطط مقابل الفعلي، القيم المتحققة، الصرف الشهري، الإنجازات والمعوقات).
            </p>
            <div class="actions-row">
                <a href="{{ route('reports.excel', array_merge(['type' => 'monthly'], $deptParam)) }}" class="btn btn-success">📥 تصدير Excel</a>
                <a href="{{ route('reports.pdf', array_merge(['type' => 'monthly'], $deptParam)) }}" class="btn btn-danger">📄 تصدير PDF</a>
            </div>
        </div>
    </div>

    <div class="card" style="border-top:4px solid #7c3aed;">
        <div class="card-body">
            <h3 class="card-title" style="margin-bottom:8px;">💰 تقرير التكاليف والموازنات</h3>
            <p class="text-muted" style="font-size:13px;min-height:58px;">
                مقارنة الموازنة المعتمدة ومصادر التمويل (ذاتي، منح، خارجي) مع التكلفة المخططة والصرف الفعلي والمتبقي لكل دائرة.
            </p>
            <div class="actions-row">
                <a href="{{ route('reports.excel', array_merge(['type' => 'budgets'], $deptParam)) }}" class="btn btn-success">📥 تصدير Excel</a>
                <a href="{{ route('reports.pdf', array_merge(['type' => 'budgets'], $deptParam)) }}" class="btn btn-danger">📄 تصدير PDF</a>
            </div>
        </div>
    </div>

    <div class="card" style="border-top:4px solid #dc2626;">
        <div class="card-body">
            <h3 class="card-title" style="margin-bottom:8px;">⚠️ تقرير المخاطر والمعوقات</h3>
            <p class="text-muted" style="font-size:13px;min-height:58px;">
                سجل المخاطر المصنفة حسب درجة الخطورة (الاحتمالية × الأثر) وخطط المعالجة والتخفيف وحالة كل خطر.
            </p>
            <div class="actions-row">
                <a href="{{ route('reports.excel', array_merge(['type' => 'risks'], $deptParam)) }}" class="btn btn-success">📥 تصدير Excel</a>
                <a href="{{ route('reports.pdf', array_merge(['type' => 'risks'], $deptParam)) }}" class="btn btn-danger">📄 تصدير PDF</a>
            </div>
        </div>
    </div>
</div>
@endsection
