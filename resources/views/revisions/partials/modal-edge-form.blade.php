{{--
    vars required:
      $action       — RevisionAction|null (null = new action)
      $edgeTypes    — Collection<EdgeType> with startVertex/endVertex loaded
      $actionType   — string ('create_edge' | 'delete_edge')
      $allActions   — Collection<RevisionAction> (for target ref dropdown)
--}}
@php
    $isCreate = $actionType === 'create_edge';
    $isDelete = $actionType === 'delete_edge';
    $createVertexActions = $allActions->where('action.value', 'create_vertex');
@endphp

@if ($isCreate)
    {{-- Edge type select --}}
    <div class="mb-3">
        <label class="col-form-label fw-semibold">Edge 類型</label>
        <select name="edge_type_label" id="modal_edge_type_label"
                class="form-select @error('edge_type_label') is-invalid @enderror" required>
            <option value="">— 請選擇 —</option>
            @foreach ($edgeTypes as $et)
                <option value="{{ $et->age_label_name }}"
                    data-start="{{ $et->startVertex->age_label_name }}"
                    data-end="{{ $et->endVertex->age_label_name }}"
                    {{ old('edge_type_label', $action?->edge_type_label) === $et->age_label_name ? 'selected' : '' }}>
                    {{ $et->name }} ({{ $et->startVertex->name }} → {{ $et->endVertex->name }})
                </option>
            @endforeach
        </select>
        @error('edge_type_label')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Start vertex --}}
    <div class="mb-3">
        <label class="col-form-label fw-semibold">起始 Vertex</label>
        @if ($createVertexActions->isNotEmpty())
            <div class="mb-2">
                <div class="form-text mb-1">指向本修訂內的新增 Vertex 操作：</div>
                <select name="start_vertex_ref_order"
                        class="form-select @error('start_vertex_ref_order') is-invalid @enderror">
                    <option value="">— 不選擇 —</option>
                    @foreach ($createVertexActions as $ca)
                        <option value="{{ $ca->order }}"
                            data-vertex-label="{{ $ca->vertex_type_label }}"
                            {{ (string) old('start_vertex_ref_order', $action?->start_vertex_ref_order) === (string) $ca->order ? 'selected' : '' }}>
                            #{{ $ca->order }}：新增 {{ $ca->vertex_type_label }} Vertex
                        </option>
                    @endforeach
                </select>
                @error('start_vertex_ref_order')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif
        <div class="form-text mb-1">或直接輸入既有 Vertex AGE ID：</div>
        <input type="number" name="start_vertex_age_id"
               class="form-control @error('start_vertex_age_id') is-invalid @enderror"
               value="{{ old('start_vertex_age_id', $action?->start_vertex_age_id) }}"
               placeholder="起始 Vertex AGE ID">
        @error('start_vertex_age_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- End vertex --}}
    <div class="mb-3">
        <label class="col-form-label fw-semibold">終止 Vertex</label>
        @if ($createVertexActions->isNotEmpty())
            <div class="mb-2">
                <div class="form-text mb-1">指向本修訂內的新增 Vertex 操作：</div>
                <select name="end_vertex_ref_order"
                        class="form-select @error('end_vertex_ref_order') is-invalid @enderror">
                    <option value="">— 不選擇 —</option>
                    @foreach ($createVertexActions as $ca)
                        <option value="{{ $ca->order }}"
                            data-vertex-label="{{ $ca->vertex_type_label }}"
                            {{ (string) old('end_vertex_ref_order', $action?->end_vertex_ref_order) === (string) $ca->order ? 'selected' : '' }}>
                            #{{ $ca->order }}：新增 {{ $ca->vertex_type_label }} Vertex
                        </option>
                    @endforeach
                </select>
                @error('end_vertex_ref_order')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif
        <div class="form-text mb-1">或直接輸入既有 Vertex AGE ID：</div>
        <input type="number" name="end_vertex_age_id"
               class="form-control @error('end_vertex_age_id') is-invalid @enderror"
               value="{{ old('end_vertex_age_id', $action?->end_vertex_age_id) }}"
               placeholder="終止 Vertex AGE ID">
        @error('end_vertex_age_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endif

@if ($isDelete)
    <div class="mb-3">
        <label class="col-form-label fw-semibold">目標 Edge</label>
        <div class="form-text mb-1">輸入既有 Edge AGE ID：</div>
        <input type="number" name="target_age_id"
               class="form-control @error('target_age_id') is-invalid @enderror"
               value="{{ old('target_age_id', $action?->target_age_id) }}"
               placeholder="AGE edge ID"
               required>
        @error('target_age_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endif
