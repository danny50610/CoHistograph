@extends('layouts.app')

@php
    $isEditMode = isset($edgeType);
    $methodText = $isEditMode ? '編輯' : '新增';
    $ageLabelNameLocked = $ageLabelNameLocked ?? false;
@endphp

@section('title', $methodText . ' Edge')

@section('content')
    <div class="container">
        <h1>{{ $methodText }} Edge</h1>
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
                    <x-forms.select id="start_vertex_id" label="起始節點" :value="$edgeType->start_vertex_id ?? ''" :options="$vertexOptions" required />
                    <x-forms.select id="end_vertex_id" label="結束節點" :value="$edgeType->end_vertex_id ?? ''" :options="$vertexOptions" required />

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
