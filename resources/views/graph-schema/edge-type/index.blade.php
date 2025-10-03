@extends('layouts.app')

@section('title', 'Edge - Graph Schema 管理')

@section('content')
    <div class="container">
        @include('graph-schema.buttons', ['type' => 'edge'])

        <h1>Graph Schema - Edge 管理</h1>

        @permission('graph-schema.manage')
            <a href="{{ route('graph-schema.edge-type.create') }}" class="btn btn-primary mb-2">
                <i class="fa-solid fa-plus"></i> 新增
            </a>
        @endpermission

        @forelse ($edgeTypeList as $edgeType)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ $edgeType->name }}
                        <span class=text-body-secondary>({{ $edgeType->age_label_name }})</span>
                        <a href="{{ route('graph-schema.edge-type.show', $edgeType) }}"><i class="fa-solid fa-receipt"></i></a>
                    </h5>
                    <p class="card-text">{{ $edgeType->description }}</p>
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body">
                    目前沒有 Edge
                </div>
            </div>
        @endforelse
        <div class="mt-2">
            {{ $edgeTypeList->links() }}
        </div>
    </div>
@endsection
