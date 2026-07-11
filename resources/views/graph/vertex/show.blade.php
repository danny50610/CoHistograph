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

        <h2>Edge</h2>
        @php $hasEdges = false; @endphp
        @foreach ($edgeInfoList as $edgeInfo)
            @foreach ($edgeInfo['edges'] as $edgeItem)
                @php
                    $hasEdges = true;
                    $edgePropertyGroups = app(\App\Support\LocalizedPropertyGrouper::class)->group(
                        $edgeInfo['type']->properties,
                        (array) ($edgeItem['edge']->properties ?? []),
                    );
                @endphp
                <div class="card mb-2">
                    <div class="card-body">
                        <p class="mb-2">
                            {{ $edgeInfo['type']->name }}
                            <a href="{{ route('graph-schema.edge-type.show', [$edgeInfo['type']]) }}"><i class="fa-solid fa-circle-info"></i></a>
                            →
                            <a href="{{ route('graph.vertex.show', ['vertex' => $edgeItem['vertex']->id]) }}">
                                {{ $edgeItem['displayName'] }}
                            </a>
                        </p>

                        @if ($edgePropertyGroups !== [])
                            <x-localized-property-groups :groups="$edgePropertyGroups" />
                        @endif
                    </div>
                </div>
            @endforeach
        @endforeach
        @if (! $hasEdges)
            <span>目前沒有任何 Edge</span>
        @endif
    </div>
@endsection
