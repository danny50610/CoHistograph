@extends('layouts.app')

@section('title', $vertexType->name . ' - Vertex')

@section('content')
    <div class="container">
        <h1>Vertex - {{ $vertexType->name }}</h1>

        @forelse ($vertexList as $vertex)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ $vertex->v->properties['name'] }}
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
