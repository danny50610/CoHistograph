@extends('layouts.app')

@php
    $isEditMode = isset($vertexProperty);
    $methodText = $isEditMode ? '編輯' : '新增';
@endphp

@section('title', $methodText . ' Vertex Property')

@section('content')
    <div class="container">
        <h1>{{ $methodText }} Vertex Property</h1>
        <div class="card">
            <div class="card-body">
                <form role="form" method="POST"
                      action="{{ $isEditMode ? route('graph-schema.vertex-property.update', [$vertexType, $vertexProperty]) : route('graph-schema.vertex-property.store', [$vertexType]) }}">
                    @if($isEditMode)
                        @method('patch')
                    @endif
                    @csrf

                    <x-forms.input id="name" label="名稱" :value="$vertexProperty->name ?? ''" required />
                    <x-forms.input id="description" label="描述" :value="$vertexProperty->description ?? ''" />
                    <x-forms.input id="age_property_name" label="Property 名稱" :value="$vertexProperty->age_property_name ?? ''" required />
                    <x-forms.input id="age_property_type" label="Property Type" :value="$vertexProperty->age_property_type->value ?? ''" required />

                    <div class="row mb-2">
                        <div class="col-md-10 ms-auto">
                            <button type="submit" class="btn btn-primary">儲存</button>
                            <a href="{{ route('graph-schema.vertex-type.show', [$vertexType]) }}" class="btn btn-secondary">返回</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
