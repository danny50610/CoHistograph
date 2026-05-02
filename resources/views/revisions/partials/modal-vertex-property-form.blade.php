{{--
    vars required:
      $action       — RevisionAction|null
      $vertexTypes  — Collection<VertexType> with properties loaded
      $actionType   — string ('create_vertex_property'|'update_vertex_property'|'delete_vertex_property')
      $allActions   — Collection<RevisionAction>
--}}
@php
    $isCreate = $actionType === 'create_vertex_property';
    $isUpdate = $actionType === 'update_vertex_property';
    $isDelete = $actionType === 'delete_vertex_property';
    $createVertexActions = $allActions->where('action.value', 'create_vertex');
@endphp

{{-- Target vertex --}}
<div class="mb-3">
    <label class="col-form-label fw-semibold">目標 Vertex</label>
    @if ($createVertexActions->isNotEmpty())
        <div class="mb-2">
            <div class="form-text mb-1">指向本修訂內的新增 Vertex 操作：</div>
            <select name="target_ref_order"
                    id="modal_vp_target_ref_order"
                    class="form-select @error('target_ref_order') is-invalid @enderror"
                    onchange="revisionSyncVertexPropertyOptions(this)">
                <option value="">— 不選擇 —</option>
                @foreach ($createVertexActions as $ca)
                    <option value="{{ $ca->order }}"
                        data-vertex-label="{{ $ca->vertex_type_label }}"
                        {{ (string) old('target_ref_order', $action?->target_ref_order) === (string) $ca->order ? 'selected' : '' }}>
                        #{{ $ca->order }}：新增 {{ $ca->vertex_type_label }} Vertex
                    </option>
                @endforeach
            </select>
            @error('target_ref_order')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif
    <div class="form-text mb-1">或直接輸入既有 Vertex AGE ID：</div>
    <input type="number" name="target_age_id"
           class="form-control @error('target_age_id') is-invalid @enderror"
           value="{{ old('target_age_id', $action?->target_age_id) }}"
           placeholder="AGE vertex ID">
    @error('target_age_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Property name — cascading from vertex type, or manual input --}}
<div class="mb-3">
    <label class="col-form-label fw-semibold">屬性名稱 (AGE property name)</label>

    {{-- Cascading options grouped by VertexType --}}
    <select name="age_property_name" id="modal_vp_property_name"
            class="form-select @error('age_property_name') is-invalid @enderror" required>
        <option value="">— 請選擇或手動輸入 —</option>
        @foreach ($vertexTypes as $vt)
            @if ($vt->properties->isNotEmpty())
                <optgroup label="{{ $vt->name }}" data-vertex-label="{{ $vt->age_label_name }}">
                    @foreach ($vt->properties as $prop)
                        <option value="{{ $prop->age_property_name }}"
                            {{ old('age_property_name', $action?->age_property_name) === $prop->age_property_name ? 'selected' : '' }}>
                            {{ $prop->name }} ({{ $prop->age_property_name }})
                        </option>
                    @endforeach
                </optgroup>
            @endif
        @endforeach
    </select>
    @error('age_property_name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Value — only for create/update --}}
@if ($isCreate || $isUpdate)
    <div class="mb-3">
        <label class="col-form-label fw-semibold">屬性值</label>
        <input type="text" name="value"
               class="form-control @error('value') is-invalid @enderror"
               value="{{ old('value', $action?->value) }}"
               placeholder="屬性值" required>
        @error('value')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endif
