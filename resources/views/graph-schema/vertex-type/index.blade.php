@extends('layouts.app')

@section('title', 'Vertex - Graph Schema 管理')

@section('content')
    <div class="container">
        @include('graph-schema.buttons', ['type' => 'vertex'])

        <h1>Graph Schema - Vertex 管理</h1>

        @permission('graph-schema.manage')
            <a href="{{ route('graph-schema.vertex-type.create') }}" class="btn btn-primary mb-2">
                <i class="fa-solid fa-plus"></i> 新增
            </a>
        @endpermission

        @forelse ($vertexTypeList as $vertexType)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ $vertexType->name }}
                        <span class=text-body-secondary>({{ $vertexType->age_label_name }})</span>
                        <a href="{{ route('graph-schema.vertex-type.show', $vertexType) }}"><i class="fa-solid fa-receipt"></i></a>
                    </h5>
                    <p class="card-text">{{ $vertexType->description }}</p>
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body">
                    目前沒有 Vertex
                </div>
            </div>
        @endforelse
        <div class="mt-2">
            {{ $vertexTypeList->links() }}
        </div>
    </div>
@endsection
