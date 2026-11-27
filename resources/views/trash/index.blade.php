@extends('layouts.app')
@section('title', 'سلة المحذوفات واستعادة البيانات')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">🗑️ سلة المحذوفات واستعادة البيانات</h2>
        <p class="page-subtitle">استعراض كافة السجلات المحذوفة مع إمكانية استعادتها فردياً أو جماعياً أو حذفها نهائياً</p>
    </div>
    @if(count($items) > 0)
        <form method="POST" action="{{ route('trash.restore_all') }}" onsubmit="return confirm('استعادة جميع العناصر المعروضة من سلة المحذوفات؟');">
            @csrf
            <input type="hidden" name="type" value="{{ $selectedType }}">
            <button type="submit" class="btn btn-success">↩ استعادة الكل ({{ count($items) }})</button>
        </form>
    @endif
</div>

<!-- Type Filter Pills -->
<div class="card">
    <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('trash.index', ['type' => 'all']) }}" class="btn btn-sm {{ $selectedType === 'all' ? 'btn-primary' : 'btn-outline' }}">
            الكل ({{ collect($counts)->sum('count') }})
        </a>
        @foreach($counts as $key => $info)
            @if($info['count'] > 0 || $selectedType === $key)
                <a href="{{ route('trash.index', ['type' => $key]) }}" class="btn btn-sm {{ $selectedType === $key ? 'btn-primary' : 'btn-outline' }}">
                    {{ $info['label'] }} ({{ $info['count'] }})
                </a>
            @endif
        @endforeach
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>النوع</th>
                <th>العنصر المحذوف</th>
                <th>تاريخ الحذف</th>
                <th class="text-center">الإجراءات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td><span class="badge badge-secondary">{{ $item['type_label'] }}</span></td>
                    <td class="fw-bold">{{ $item['label'] }}</td>
                    <td>{{ $item['deleted_at']?->format('Y-m-d H:i') }}</td>
                    <td class="text-center" style="white-space:nowrap;">
                        <form method="POST" action="{{ route('trash.restore', [$item['type'], $item['id']]) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">↩ استعادة</button>
                        </form>
                        <form method="POST" action="{{ route('trash.force_delete', [$item['type'], $item['id']]) }}" style="display:inline;" onsubmit="return confirm('تحذير: سيتم حذف هذا العنصر نهائياً ولا يمكن التراجع. هل أنت متأكد؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">حذف نهائي</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted" style="padding:36px;">سلة المحذوفات فارغة حالياً ✨</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
