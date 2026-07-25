@extends('layouts.app')

@section('title', $displayName . ' - ' . $vertexType->name . ' - Vertex')

@section('content')
    <div class="container">
        <a href="{{ route('graph.vertex.index', ['type' => $vertexType->age_label_name]) }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 返回</a>

        <h1>Vertex - {{ $vertexType->name }} - {{ $displayName }}</h1>

        <a href="{{ route('graph-schema.vertex-type.show', $vertexType) }}" class="btn btn-primary mb-2">
            <i class="fa-solid fa-circle-info"></i> Schema
        </a>

        <h2>Properties</h2>
        <div class="card mb-2">
            <div class="card-body">
                <x-localized-property-groups :groups="$propertyGroups" />
            </div>
        </div>

        @include('graph.vertex.partials.edge-section', [
            'heading' => '連出 Edge',
            'emptyMessage' => '目前沒有任何連出 Edge',
            'edgeInfoList' => $outgoingEdges,
            'useReverseName' => false,
        ])

        @include('graph.vertex.partials.edge-section', [
            'heading' => '連入 Edge',
            'emptyMessage' => '目前沒有任何連入 Edge',
            'edgeInfoList' => $incomingEdges,
            'useReverseName' => true,
        ])
    </div>
@endsection
