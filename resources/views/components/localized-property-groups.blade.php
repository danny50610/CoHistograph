@props(['groups'])

@php
    $caster = app(\App\Support\PropertyValueCaster::class);
@endphp

<dl class="row mb-0">
    @forelse ($groups as $group)
        @if ($group['is_localized'])
            <dt class="col-md-2">{{ $group['title'] }}</dt>
            <dd class="col-md-10 mb-0">
                @foreach ($group['members'] as $member)
                    <div @if (! $loop->last) class="mb-1" @endif>
                        {{ $member['locale_label'] }}：{{ $caster->formatForDisplay($member['value'], $member['property']->age_property_type) }}
                    </div>
                @endforeach
            </dd>
        @else
            <dt class="col-md-2">{{ $group['title'] }}</dt>
            <dd class="col-md-10 mb-0">{{ $caster->formatForDisplay($group['members'][0]['value'] ?? null, $group['members'][0]['property']->age_property_type) }}</dd>
        @endif
    @empty
        <span>目前沒有任何 Property</span>
    @endforelse
</dl>
