@extends('layouts.app')

@section('title', $vertexType->name . ' - Vertex - Graph Schema 管理')

@section('content')
    <div class="container">
        <a href="{{ route('graph-schema.vertex-type.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 返回</a>

        <h1>Graph Schema - Vertex - {{ $vertexType->name }}</h1>

        <div class="mb-2">
            <a href="{{ route('graph.vertex.index', ['type' => $vertexType->age_label_name]) }}" class="btn btn-primary">
                <i class="fa-solid fa-receipt"></i> Data
            </a>
            @permission('graph-schema.manage')
            {{ html()->form('DELETE', route('graph-schema.vertex-type.destroy', [$vertexType]))->style('display: inline')->attribute('onSubmit', "return confirm('確定要刪除此 Vertex 嗎？');")->open() }}
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> 刪除</button>
            {{ html()->form()->close() }}
            @endpermission
        </div>

        <h2>基本資料</h2>
        @permission('graph-schema.manage')
        <a href="{{ route('graph-schema.vertex-type.edit', $vertexType) }}" class="btn btn-primary mb-2"><i class="fa-solid fa-pen-to-square"></i> 編輯</a>
        @endpermission
        <div class="card mb-2">
            <div class="card-body">
                <dl class="row">
                    <dt class="col-md-2">名稱</dt>
                    <dd class="col-md-10">{{ $vertexType->name }}</dd>

                    <dt class="col-md-2">Label 名稱</dt>
                    <dd class="col-md-10">{{ $vertexType->age_label_name }}</dd>

                    <dt class="col-md-2">描述</dt>
                    <dd class="col-md-10">{{ $vertexType->description }}</dd>

                    <dt class="col-md-2">顯示用 Property</dt>
                    <dd class="col-md-10">{{ $showPropertyNameLabel }}</dd>

                    <dt class="col-md-2">Overview 顯示</dt>
                    <dd class="col-md-10">
                        @if ($vertexType->overview_order !== null)
                            顯示（順序：{{ $vertexType->overview_order }}）
                        @else
                            不顯示
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        <h2>Properties</h2>
        @permission('graph-schema.manage')
        <a href="{{ route('graph-schema.vertex-property.create', [$vertexType]) }}" class="btn btn-primary mb-2"><i class="fa-solid fa-plus"></i> 新增</a>
        @endpermission
        <div class="card mb-2">
            <div class="card-body">
                @include('graph-schema.partials.property-schema-groups', [
                    'groups' => $propertyGroups,
                    'typeModel' => $vertexType,
                    'propertyShowRoute' => 'graph-schema.vertex-property.show',
                ])
            </div>
        </div>

        <h2>連入 Edge</h2>
        <div class="card mb-2">
            <div class="card-body">
                @forelse ($vertexType->endEdgeTypes as $edgeType)
                    <dl class="row mb-0">
                        <dt class="col-md-2">
                            <a href="{{ route('graph-schema.edge-type.show', [$edgeType]) }}">
                                {{ $edgeType->reverse_name == '' ? $edgeType->name : $edgeType->reverse_name }}</a>
                            <span class=text-body-secondary>({{ $edgeType->age_label_name }})</span>
                        </dt>
                        <dd class="col-md-10 mb-0">
                            ←
                            <a href="{{ route('graph-schema.vertex-type.show', [$edgeType->startVertex]) }}">
                                {{ $edgeType->startVertex->name}}
                            </a>
                        </dd>
                    </dl>
                @empty
                    <span>沒有任何連入 Edge</span>
                @endforelse
            </div>
        </div>

        <h2>連出 Edge</h2>
        <div class="card mb-2">
            <div class="card-body">
                @forelse ($vertexType->startEdgeTypes as $edgeType)
                    <dl class="row mb-0">
                        <dt class="col-md-2">
                            <a href="{{ route('graph-schema.edge-type.show', [$edgeType]) }}">
                                {{ $edgeType->name }}</a>
                            <span class=text-body-secondary>({{ $edgeType->age_label_name }})</span>
                        </dt>
                        <dd class="col-md-10 mb-0">
                            →
                            <a href="{{ route('graph-schema.vertex-type.show', [$edgeType->endVertex]) }}">
                                {{ $edgeType->endVertex->name}}
                            </a>
                        </dd>
                    </dl>
                @empty
                    <span>沒有任何連出 Edge</span>
                @endforelse
            </div>
        </div>
    </div>
@endsection
