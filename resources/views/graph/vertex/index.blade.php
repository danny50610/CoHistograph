@extends('layouts.app')

@section('title', $vertexType->name . ' - Vertex')

@section('content')
    <div class="container">
        <a href="{{ route('graph.vertex.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 返回</a>

        <h1>Vertex - {{ $vertexType->name }}</h1>

        <a href="{{ route('graph-schema.vertex-type.show', $vertexType) }}" class="btn btn-primary mb-2">
            <i class="fa-solid fa-circle-info"></i> Schema
        </a>

        @forelse ($vertexList as $vertex)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ $vertex->v->properties[$vertexType->show_property_name] }}
                        <a href="{{ route('graph.vertex.show', ['vertex' => $vertex->v->id]) }}"><i class="fa-solid fa-receipt"></i></a>
                    </h5>
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body">
                    目前沒有 {{ $vertexType->name }}
                </div>
            </div>
        @endforelse
    </div>
@endsection
