@extends('layouts.app')

@section('title', $vertex->properties['name'] . ' - ' . $vertexType->name . ' - Vertex')

@section('content')
    <div class="container">
        <a href="{{ route('graph.vertex.index', ['type' => $vertexType->age_label_name]) }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 返回</a>

        <h1>Vertex - {{ $vertexType->name }} - {{ $vertex->properties['name'] }}</h1>

        <h2>Properties</h2>
        <div class="card mb-2">
            <div class="card-body">
                <dl class="row">
                    @forelse ($vertexType->properties as $properties)
                        <dt class="col-md-2">
                            {{ $properties->name }}
                            <span class=text-body-secondary>({{ $properties->age_property_name }})</span>
                            <a href="{{ route('graph-schema.vertex-property.show', [$vertexType, $properties]) }}"><i class="fa-solid fa-receipt"></i></a>
                        </dt>
                        <dd class="col-md-10">
                            <span class="badge text-bg-info">{{ $properties->age_property_type }}</span>
                            {{ $vertex->properties[$properties->age_property_name] }}
                        </dd>
                    @empty
                        <span>目前沒有任何 Property</span>
                    @endforelse
                </dl>
            </div>
        </div>
    </div>
@endsection
