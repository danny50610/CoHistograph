@extends('layouts.app')

@php
    $isEditMode = isset($vertexType);
    $methodText = $isEditMode ? '編輯' : '新增';
@endphp

@section('title', $methodText . ' Vertex')

@section('content')
    <div class="container">
        <h1>{{ $methodText }} Vertex</h1>
        <div class="card">
            <div class="card-body">
                <form role="form" method="POST"
                      action="{{ $isEditMode ? route('graph-schema.vertex-type.update', [$vertexType]) : route('graph-schema.vertex-type.store') }}">
                    @if($isEditMode)
                        @method('patch')
                    @endif
                    @csrf

                    <x-forms.input id="name" label="名稱" :value="$vertexType->name ?? ''" required />
                    <x-forms.input id="age_label_name" label="Label 名稱" :value="$vertexType->age_label_name ?? ''" required />
                    <x-forms.input id="description" label="描述" :value="$vertexType->description ?? ''" />

                    <div class="row mb-2">
                        <div class="col-md-10 ms-auto">
                            @if ($isEditMode)
                                <button type="submit" class="btn btn-primary">更新</button>
                                <a href="{{ route('graph-schema.vertex-type.show', [$vertexType]) }}" class="btn btn-secondary">返回</a>
                            @else
                                <button type="submit" class="btn btn-primary">新增</button>
                                <a href="{{ route('graph-schema.vertex-type.index') }}" class="btn btn-secondary">返回列表</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
