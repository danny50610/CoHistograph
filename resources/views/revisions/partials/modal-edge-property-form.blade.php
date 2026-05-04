{{--
    vars required:
      $action       — RevisionAction|null
      $edgeTypes    — Collection<EdgeType> with properties loaded
      $actionType   — string ('create_edge_property'|'update_edge_property'|'delete_edge_property')
      $allActions   — Collection<RevisionAction>
--}}
@php
    $isCreate = $actionType === 'create_edge_property';
    $isUpdate = $actionType === 'update_edge_property';
    $isDelete = $actionType === 'delete_edge_property';
    $createEdgeActions = $allActions->where('action.value', 'create_edge');
@endphp

{{-- Target edge --}}
<div class="mb-3">
    <label class="col-form-label fw-semibold">目標 Edge</label>

    @if ($createEdgeActions->isNotEmpty())
        <div class="mb-2">
            <div class="form-text mb-1">指向本修訂內的新增 Edge 操作：</div>
            <select name="target_ref_order"
                    id="modal_ep_target_ref_order"
                    class="form-select @error('target_ref_order') is-invalid @enderror"
                    onchange="revisionSyncEdgePropertyOptions(this)">
                <option value="">— 不選擇 —</option>
                @foreach ($createEdgeActions as $ca)
                    <option value="{{ $ca->order }}"
                        data-edge-label="{{ $ca->edge_type_label }}"
                        {{ (string) old('target_ref_order', $action?->target_ref_order) === (string) $ca->order ? 'selected' : '' }}>
                        #{{ $ca->order + 1 }}：新增 {{ $ca->edge_type_label }} Edge
                    </option>
                @endforeach
            </select>
            @error('target_ref_order')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="form-text mb-1">或直接輸入既有 Edge AGE ID：</div>
    <input type="number" name="target_age_id"
           class="form-control @error('target_age_id') is-invalid @enderror"
           value="{{ old('target_age_id', $action?->target_age_id) }}"
           placeholder="AGE edge ID">
    @error('target_age_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Property name — cascading from edge type --}}
<div class="mb-3">
    <label class="col-form-label fw-semibold">屬性名稱 (AGE property name)</label>
    <select name="age_property_name" id="modal_ep_property_name"
            class="form-select @error('age_property_name') is-invalid @enderror" required>
        <option value="">— 請選擇或手動輸入 —</option>
        @foreach ($edgeTypes as $et)
            @if ($et->properties->isNotEmpty())
                <optgroup label="{{ $et->name }}" data-edge-label="{{ $et->age_label_name }}">
                    @foreach ($et->properties as $prop)
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
