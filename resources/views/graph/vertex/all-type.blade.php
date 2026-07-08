@extends('layouts.app')

@section('title', 'Vertex')

@section('content')
    <div class="container">
        <a href="{{ route('overview') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 返回</a>

        <h1>Vertex</h1>

        @foreach ($vertexInfoList as $vertexInfo)
            <div class=mb-2>
                <h2>{{ $vertexInfo['type']->name }}</h2>
                @foreach ($vertexInfo['vertexList'] as $vertex)
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">{{ $vertex->displayName }}
                                <a href="{{ route('graph.vertex.show', ['vertex' => $vertex->v->id]) }}"><i class="fa-solid fa-receipt"></i></a>
                            </h5>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
        <div class="mt-2">
            {{ $vertexTypeList->links() }}
        </div>
    </div>
@endsection
