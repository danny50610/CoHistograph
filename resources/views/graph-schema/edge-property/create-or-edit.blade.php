@extends('layouts.app')

@php
    $isEditMode = isset($edgeProperty);
    $methodText = $isEditMode ? '編輯' : '新增';
@endphp

@section('title', $methodText . ' Edge Property')

@section('content')
    <div class="container">
        <h1>{{ $methodText }} Edge Property</h1>
        <div class="card">
            <div class="card-body">
                <form role="form" method="POST"
                      action="{{ $isEditMode ? route('graph-schema.edge-property.update', [$edgeType, $edgeProperty]) : route('graph-schema.edge-property.store', [$edgeType]) }}">
                    @if($isEditMode)
                        @method('patch')
                    @endif
                    @csrf

                    <x-forms.input id="name" label="名稱" :value="$edgeProperty->name ?? ''" required />
                    <x-forms.input id="description" label="描述" :value="$edgeProperty->description ?? ''" />
                    <x-forms.input id="age_property_name" label="Property 名稱" :value="$edgeProperty->age_property_name ?? ''" required />
                    <x-forms.input id="age_property_type" label="Property Type" :value="$edgeProperty->age_property_type->value ?? ''" required />

                    <div class="row mb-2">
                        <div class="col-md-10 ms-auto">
                            @if ($isEditMode)
                                <button type="submit" class="btn btn-primary">更新</button>
                            @else
                                <button type="submit" class="btn btn-primary">新增</button>
                            @endif
                            <a href="{{ route('graph-schema.edge-type.show', [$edgeType]) }}" class="btn btn-secondary">返回</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
