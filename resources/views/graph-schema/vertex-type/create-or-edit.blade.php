@extends('layouts.app')

@php
    $isEditMode = isset($vertexType);
    $methodText = $isEditMode ? '編輯' : '新增';
    $showOnOverview = (bool) old('show_on_overview', $isEditMode && $vertexType->overview_order !== null);
    $overviewOrder = old('overview_order', $isEditMode ? $vertexType->overview_order : '');
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
                    <x-forms.input id="age_label_name" label="Label 名稱" :value="$vertexType->age_label_name ?? ''" helpText="只能包含小寫英文、數字、_" required />
                    <x-forms.input id="description" label="描述" :value="$vertexType->description ?? ''" />
                    @if($isEditMode)
                        <x-forms.select id="show_property_name" label="顯示 property" :value="$vertexType->show_property_name ?? ''" :options="$propertyOptions" />
                    @endif

                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">Overview 顯示</label>
                        <div class="col-md-10" style="padding-top: calc(.5rem - 1px * 2);">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input"
                                       name="show_on_overview" value="1" id="show_on_overview"
                                       @if($showOnOverview) checked @endif>
                                <label class="custom-control-label" for="show_on_overview">在 Overview 顯示</label>
                            </div>
                        </div>
                    </div>
                    <x-forms.input id="overview_order" label="Overview 順序" type="number"
                                   :value="$overviewOrder" helpText="數字越小越前面，範圍 1–255" />

                    <div class="row mb-2">
                        <div class="col-md-10 ms-auto">
                            <button type="submit" class="btn btn-primary">儲存</button>
                            @if ($isEditMode)
                                <a href="{{ route('graph-schema.vertex-type.show', [$vertexType]) }}" class="btn btn-secondary">返回</a>
                            @else
                                <a href="{{ route('graph-schema.vertex-type.index') }}" class="btn btn-secondary">返回列表</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
