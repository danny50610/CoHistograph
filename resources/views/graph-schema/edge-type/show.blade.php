@extends('layouts.app')

@section('title', $edgeType->name . ' - Edge 類型 - Graph Schema 管理')

@section('content')
    <div class="container">
        <a href="{{ route('graph-schema.edge-type.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 返回</a>

        <h1>Graph Schema - Edge 類型 - {{ $edgeType->name }}</h1>

        @permission('graph-schema.manage')
        <div class="mb-2">
            {{ html()->form('DELETE', route('graph-schema.edge-type.destroy', [$edgeType]))->style('display: inline')->attribute('onSubmit', "return confirm('確定要刪除此 Edge 類型嗎？這會移除 schema 定義，不會透過 Revision 刪除圖上的 Edge 資料。');")->open() }}
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> 刪除 Edge 類型</button>
            {{ html()->form()->close() }}
        </div>
        @endpermission

        <h2>基本資料</h2>
        @permission('graph-schema.manage')
        <a href="{{ route('graph-schema.edge-type.edit', $edgeType) }}" class="btn btn-primary mb-2"><i class="fa-solid fa-pen-to-square"></i> 編輯</a>
        @endpermission
        <div class="card mb-2">
            <div class="card-body">
                <dl class="row">
                    <dt class="col-md-2">名稱</dt>
                    <dd class="col-md-10">{{ $edgeType->name }}</dd>

                    <dt class="col-md-2">反向名稱</dt>
                    <dd class="col-md-10">{{ $edgeType->reverse_name }}</dd>

                    <dt class="col-md-2">Label 名稱</dt>
                    <dd class="col-md-10">{{ $edgeType->age_label_name }}</dd>

                    <dt class="col-md-2">描述</dt>
                    <dd class="col-md-10">{{ $edgeType->description }}</dd>
                </dl>
            </div>
        </div>

        <h2>Properties</h2>
        @permission('graph-schema.manage')
        <a href="{{ route('graph-schema.edge-property.create', [$edgeType]) }}" class="btn btn-primary mb-2"><i class="fa-solid fa-plus"></i> 新增</a>
        @endpermission
        <div class="card mb-2">
            <div class="card-body">
                @include('graph-schema.partials.property-schema-groups', [
                    'groups' => $propertyGroups,
                    'typeModel' => $edgeType,
                    'propertyShowRoute' => 'graph-schema.edge-property.show',
                ])
            </div>
        </div>

        <h2>允許的起迄組合</h2>
        <div class="card mb-2">
            <div class="card-body">
                @forelse ($edgeType->vertexPairs as $pair)
                    <dl class="row mb-0">
                        <dt class="col-md-2">組合</dt>
                        <dd class="col-md-10">
                            <a href="{{ route('graph-schema.vertex-type.show', [$pair->startVertex]) }}">
                                {{ $pair->startVertex->name }}</a>
                            <span class="text-body-secondary">({{ $pair->startVertex->age_label_name }})</span>
                            →
                            <a href="{{ route('graph-schema.vertex-type.show', [$pair->endVertex]) }}">
                                {{ $pair->endVertex->name }}</a>
                            <span class="text-body-secondary">({{ $pair->endVertex->age_label_name }})</span>
                        </dd>
                    </dl>
                @empty
                    <span>尚未定義起迄組合</span>
                @endforelse
            </div>
        </div>

    </div>
@endsection
