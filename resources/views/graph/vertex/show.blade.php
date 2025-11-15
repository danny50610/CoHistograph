@extends('layouts.app')

@section('title', $vertex->properties[$vertexType->show_property_name] . ' - ' . $vertexType->name . ' - Vertex')

@section('content')
    <div class="container">
        <a href="{{ route('graph.vertex.index', ['type' => $vertexType->age_label_name]) }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 返回</a>

        <h1>Vertex - {{ $vertexType->name }} - {{ $vertex->properties[$vertexType->show_property_name] }}</h1>

        <a href="{{ route('graph-schema.vertex-type.show', $vertexType) }}" class="btn btn-primary mb-2">
            <i class="fa-solid fa-circle-info"></i> Schema
        </a>

        <h2>Properties</h2>
        <div class="card mb-2">
            <div class="card-body">
                <dl class="row mb-0">
                    @forelse ($vertexType->properties as $properties)
                        <dt class="col-md-2">
                            {{ $properties->name }}
                            <span class=text-body-secondary>({{ $properties->age_property_name }})</span>
                        </dt>
                        <dd class="col-md-10 mb-0">
                            {{ $vertex->properties[$properties->age_property_name] ?? '' }}
                        </dd>
                    @empty
                        <span>目前沒有任何 Property</span>
                    @endforelse
                </dl>
            </div>
        </div>

        <h2>Edge</h2>
        @forelse ($edgeInfoList as $edgeInfo)
            <div class="card mb-2">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-md-2">
                            {{ $edgeInfo['type']->name }}
                            <a href="{{ route('graph-schema.edge-type.show', [$edgeInfo['type']]) }}"><i class="fa-solid fa-circle-info"></i></a>
                        </dt>
                        <dd class="col-md-10  mb-0">
                            @foreach ($edgeInfo['edges'] as $edge)
                                <a class="d-block" href="{{ route('graph.vertex.show', ['vertex' => $edge['end_vertex']->id]) }}">
                                    {{ $edge['end_vertex']->properties[$edgeInfo['vertex_type']->show_property_name ?? 'name']}}
                                </a>
                            @endforeach
                        </dd>
                    </dl>
                </div>
            </div>
        @empty
            <span>目前沒有任何 Edge</span>
        @endforelse
    </div>
@endsection
