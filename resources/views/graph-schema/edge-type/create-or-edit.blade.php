@extends('layouts.app')

@php
    $isEditMode = isset($edgeType);
    $methodText = $isEditMode ? '編輯' : '新增';
    $ageLabelNameLocked = $ageLabelNameLocked ?? false;
    $initialPairs = old('vertex_pairs');
    if ($initialPairs === null) {
        if ($isEditMode) {
            $initialPairs = $edgeType->vertexPairs->map(fn ($pair) => [
                'start_vertex_id' => $pair->start_vertex_id,
                'end_vertex_id' => $pair->end_vertex_id,
            ])->values()->all();
        } else {
            $initialPairs = [['start_vertex_id' => '', 'end_vertex_id' => '']];
        }
    }
@endphp

@section('title', $methodText . ' Edge 類型')

@section('content')
    <div class="container">
        <h1>{{ $methodText }} Edge 類型</h1>
        <div class="card">
            <div class="card-body">
                <form role="form" method="POST"
                      action="{{ $isEditMode ? route('graph-schema.edge-type.update', [$edgeType]) : route('graph-schema.edge-type.store') }}">
                    @if($isEditMode)
                        @method('patch')
                    @endif
                    @csrf

                    <x-forms.input id="name" label="名稱" :value="$edgeType->name ?? ''" required />
                    <x-forms.input id="reverse_name" label="反向名稱" :value="$edgeType->reverse_name ?? ''" />
                    <x-forms.input
                        id="age_label_name"
                        label="Label 名稱"
                        :value="$edgeType->age_label_name ?? ''"
                        :helpText="$ageLabelNameLocked ? '圖資料庫中已有此類型的資料，無法變更 Label 名稱' : '只能包含小寫英文、數字、_'"
                        :readonly="$ageLabelNameLocked"
                        required
                    />
                    <x-forms.input id="description" label="描述" :value="$edgeType->description ?? ''" />

                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">
                            起迄節點組合<span class="text-danger">*</span>
                        </label>
                        <div class="col-md-10">
                            <p class="form-text mt-0 mb-2">同一 Edge Label 可定義多組允許的起點／終點 Vertex 類型。</p>
                            <div id="vertex-pairs" class="d-flex flex-column gap-2">
                                @foreach ($initialPairs as $index => $pair)
                                    <div class="row g-2 align-items-center vertex-pair-row" data-pair-index="{{ $index }}">
                                        <div class="col-md-5">
                                            <select
                                                name="vertex_pairs[{{ $index }}][start_vertex_id]"
                                                class="form-select @if ($errors->has("vertex_pairs.$index.start_vertex_id") || $errors->has('vertex_pairs')) is-invalid @endif"
                                                required
                                            >
                                                <option value=""></option>
                                                @foreach ($vertexOptions as $option)
                                                    <option value="{{ $option['value'] }}" @selected((string) ($pair['start_vertex_id'] ?? '') === (string) $option['value'])>
                                                        {{ $option['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-auto">→</div>
                                        <div class="col-md-5">
                                            <select
                                                name="vertex_pairs[{{ $index }}][end_vertex_id]"
                                                class="form-select @if ($errors->has("vertex_pairs.$index.end_vertex_id") || $errors->has('vertex_pairs')) is-invalid @endif"
                                                required
                                            >
                                                <option value=""></option>
                                                @foreach ($vertexOptions as $option)
                                                    <option value="{{ $option['value'] }}" @selected((string) ($pair['end_vertex_id'] ?? '') === (string) $option['value'])>
                                                        {{ $option['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-auto">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-pair" @if (count($initialPairs) === 1) disabled @endif>移除</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if ($errors->has('vertex_pairs'))
                                <div class="text-danger small mt-1">
                                    @foreach ($errors->get('vertex_pairs') as $message)
                                        <div>{{ $message }}</div>
                                    @endforeach
                                </div>
                            @endif
                            <button type="button" id="add-vertex-pair" class="btn btn-outline-secondary btn-sm mt-2">新增組合</button>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-10 ms-auto">
                            <button type="submit" class="btn btn-primary">儲存</button>
                            @if ($isEditMode)
                                <a href="{{ route('graph-schema.edge-type.show', [$edgeType]) }}" class="btn btn-secondary">返回</a>
                            @else
                                <a href="{{ route('graph-schema.edge-type.index') }}" class="btn btn-secondary">返回列表</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
(() => {
    const container = document.getElementById('vertex-pairs');
    const addButton = document.getElementById('add-vertex-pair');
    if (!container || !addButton) {
        return;
    }

    const vertexOptions = @json($vertexOptions);

    function optionHtml(selectedValue) {
        return ['<option value=""></option>']
            .concat(vertexOptions.map((option) => {
                const selected = String(selectedValue ?? '') === String(option.value) ? ' selected' : '';
                return `<option value="${option.value}"${selected}>${option.label}</option>`;
            }))
            .join('');
    }

    function reindexRows() {
        const rows = [...container.querySelectorAll('.vertex-pair-row')];
        rows.forEach((row, index) => {
            row.dataset.pairIndex = String(index);
            const selects = row.querySelectorAll('select');
            selects[0].name = `vertex_pairs[${index}][start_vertex_id]`;
            selects[1].name = `vertex_pairs[${index}][end_vertex_id]`;
            const removeButton = row.querySelector('.remove-pair');
            removeButton.disabled = rows.length === 1;
        });
    }

    function createRow(startValue = '', endValue = '') {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center vertex-pair-row';
        row.innerHTML = `
            <div class="col-md-5">
                <select name="vertex_pairs[0][start_vertex_id]" class="form-select" required>
                    ${optionHtml(startValue)}
                </select>
            </div>
            <div class="col-md-auto">→</div>
            <div class="col-md-5">
                <select name="vertex_pairs[0][end_vertex_id]" class="form-select" required>
                    ${optionHtml(endValue)}
                </select>
            </div>
            <div class="col-md-auto">
                <button type="button" class="btn btn-outline-danger btn-sm remove-pair">移除</button>
            </div>
        `;
        return row;
    }

    addButton.addEventListener('click', () => {
        container.appendChild(createRow());
        reindexRows();
    });

    container.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.classList.contains('remove-pair')) {
            return;
        }
        const row = target.closest('.vertex-pair-row');
        if (!row || container.querySelectorAll('.vertex-pair-row').length <= 1) {
            return;
        }
        row.remove();
        reindexRows();
    });

    reindexRows();
})();
</script>
@endpush
