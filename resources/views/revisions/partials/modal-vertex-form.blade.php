{{--
    vars required:
      $action       — RevisionAction|null (null = new action)
      $vertexTypes  — Collection<VertexType>
      $actionType   — string (e.g. 'create_vertex', 'delete_vertex')
      $allActions   — Collection<RevisionAction> (for target ref dropdown)
--}}
@php
    $isCreate = $actionType === 'create_vertex';
    $isDelete = $actionType === 'delete_vertex';
@endphp

@if ($isCreate)
    <div class="mb-3">
        <label class="col-form-label fw-semibold">Vertex 類型</label>
        <select name="vertex_type_label" class="form-select @error('vertex_type_label') is-invalid @enderror" required>
            <option value="">— 請選擇 —</option>
            @foreach ($vertexTypes as $vt)
                <option value="{{ $vt->age_label_name }}"
                    {{ old('vertex_type_label', $action?->vertex_type_label) === $vt->age_label_name ? 'selected' : '' }}>
                    {{ $vt->name }} ({{ $vt->age_label_name }})
                </option>
            @endforeach
        </select>
        @error('vertex_type_label')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text text-secondary">新 Vertex 將依此類型建立</div>
    </div>
@endif

@if ($isDelete)
    {{-- 優先指向同修訂內先前建立的 Vertex（ref），或現有 AGE id --}}
    <div class="mb-3">
        <label class="col-form-label fw-semibold">目標 Vertex</label>

        {{-- 指向本修訂 create_vertex 操作 --}}
        @php
            $createVertexActions = $allActions->where('action.value', 'create_vertex');
        @endphp
        @if ($createVertexActions->isNotEmpty())
            <div class="mb-2">
                <div class="form-text mb-1">指向本修訂內的新增 Vertex 操作：</div>
                <select name="target_ref_order" class="form-select @error('target_ref_order') is-invalid @enderror">
                    <option value="">— 不選擇 —</option>
                    @foreach ($createVertexActions as $ca)
                        <option value="{{ $ca->order }}"
                            {{ (string) old('target_ref_order', $action?->target_ref_order) === (string) $ca->order ? 'selected' : '' }}>
                            #{{ $ca->order + 1 }}：新增 {{ $ca->vertex_type_label }} Vertex
                        </option>
                    @endforeach
                </select>
                @error('target_ref_order')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div>
            <div class="form-text mb-1">或直接輸入既有 Vertex AGE ID：</div>
            <input type="number" name="target_age_id" class="form-control @error('target_age_id') is-invalid @enderror"
                   value="{{ old('target_age_id', $action?->target_age_id) }}"
                   placeholder="AGE vertex ID">
            @error('target_age_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
@endif
