@extends('layouts.app')

@php
    $isEditMode = isset($edgeType);
    $methodText = $isEditMode ? '編輯' : '新增';
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
                    <x-forms.input id="age_label_name" label="Label 名稱" :value="$edgeType->age_label_name ?? ''" helpText="只能包含小寫英文、數字、_" required />
                    <x-forms.input id="description" label="描述" :value="$edgeType->description ?? ''" />

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
