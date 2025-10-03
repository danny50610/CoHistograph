@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Overview</h1>

        {{-- TODO: 搜尋 --}}

        <a class="btn btn-primary" href="{{ route('graph.vertex.index') }}">查看所有 Vertex</a>

        @foreach ($vertexInfoList as $vertexInfo)
            <div class=mb-2>
                <h2>{{ $vertexInfo['type']->name }}</h2>
                @foreach ($vertexInfo['vertexList'] as $vertex)
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">{{ $vertex->v->properties['name'] }}
                                <a href="{{ route('graph.vertex.show', ['vertex' => $vertex->v->id]) }}"><i class="fa-solid fa-receipt"></i></a>
                            </h5>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endsection
