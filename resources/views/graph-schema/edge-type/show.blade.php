@extends('layouts.app')

@section('title', $edgeType->name . ' - Edge - Graph Schema 管理')

@section('content')
    <div class="container">
        <a href="{{ route('graph-schema.edge-type.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 返回</a>

        <h1>Graph Schema - Edge - {{ $edgeType->name }}</h1>

        <div class="mb-2">
            {{ html()->form('DELETE', route('graph-schema.edge-type.destroy', [$edgeType]))->style('display: inline')->attribute('onSubmit', "return confirm('確定要刪除此 Edge 嗎？');")->open() }}
            <button type="submit" class="btn btn-danger">刪除</button>
            {{ html()->form()->close() }}
        </div>

        <h2>基本資料</h2>
        <a href="{{ route('graph-schema.edge-type.edit', $edgeType) }}" class="btn btn-primary mb-2">編輯</a>
        <div class="card mb-2">
            <div class="card-body">
                <dl class="row">
                    <dt class="col-md-2">名稱</dt>
                    <dd class="col-md-10">{{ $edgeType->name }}</dd>

                    <dt class="col-md-2">Label 名稱</dt>
                    <dd class="col-md-10">{{ $edgeType->age_label_name }}</dd>

                    <dt class="col-md-2">描述</dt>
                    <dd class="col-md-10">{{ $edgeType->description }}</dd>
                </dl>
            </div>
        </div>

        <h2>Properties</h2>
        <a href="{{ route('graph-schema.edge-property.create', [$edgeType]) }}" class="btn btn-primary mb-2"><i class="fa-solid fa-plus"></i> 新增</a>
        <div class="card mb-2">
            <div class="card-body">
                <dl class="row">
                    @forelse ($edgeType->properties as $properties)
                        <dt class="col-md-2">
                            {{ $properties->name }}
                            <span class=text-body-secondary>({{ $properties->age_property_name }})</span>
                            <a href="{{ route('graph-schema.edge-property.show', [$edgeType, $properties]) }}"><i class="fa-solid fa-receipt"></i></a>
                        </dt>
                        <dd class="col-md-10">
                            <span class="badge text-bg-info">{{ $properties->age_property_type }}</span>
                            {{ $properties->description }}
                        </dd>
                    @empty
                        <span>目前沒有任何 Property</span>
                    @endforelse
                </dl>
            </div>
        </div>

        {{-- TODO: 連出與連入的 Vertex --}}
    </div>
@endsection
