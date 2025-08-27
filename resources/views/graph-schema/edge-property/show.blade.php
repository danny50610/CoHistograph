@extends('layouts.app')

@section('title', $edgeProperty->name . ' - Edge Property - Graph Schema 管理')

@section('content')
    <div class="container">
        <a href="{{ route('graph-schema.edge-type.show', [$edgeType]) }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 返回</a>
        <h1>Graph Schema - Edge Property - {{ $edgeProperty->name }}</h1>
        <a href="{{ route('graph-schema.vertex-property.edit', [$edgeType, $edgeProperty]) }}" class="btn btn-primary mb-2">編輯</a>
        <div class="card mb-2">
            <div class="card-body">
                <dl class="row">
                    <dt class="col-md-2">名稱</dt>
                    <dd class="col-md-10">{{ $edgeProperty->name }}</dd>

                    <dt class="col-md-2">描述</dt>
                    <dd class="col-md-10">{{ $edgeProperty->description }}</dd>

                    <dt class="col-md-2">Property 名稱</dt>
                    <dd class="col-md-10">{{ $edgeProperty->age_property_name }}</dd>

                    <dt class="col-md-2">Property Type</dt>
                    <dd class="col-md-10">{{ $edgeProperty->age_property_type }}</dd>
                </dl>
            </div>
        </div>
    </div>
@endsection
