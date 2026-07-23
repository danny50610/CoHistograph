<h2>{{ $heading }}</h2>
@php $hasEdges = false; @endphp
@foreach ($edgeInfoList as $edgeInfo)
    @foreach ($edgeInfo['edges'] as $edgeItem)
        @php
            $hasEdges = true;
            $edgeTypeName = $useReverseName && $edgeInfo['type']->reverse_name !== ''
                ? $edgeInfo['type']->reverse_name
                : $edgeInfo['type']->name;
            $edgePropertyGroups = app(\App\Support\LocalizedPropertyGrouper::class)->group(
                $edgeInfo['type']->properties,
                (array) ($edgeItem['edge']->properties ?? []),
            );
        @endphp
        <div class="card mb-2">
            <div class="card-body">
                <p class="mb-2">
                    {{ $edgeTypeName }}
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
    <span>{{ $emptyMessage }}</span>
@endif
