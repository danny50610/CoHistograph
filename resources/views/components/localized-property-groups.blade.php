@props(['groups'])

<dl class="row mb-0">
    @forelse ($groups as $group)
        @if ($group['is_localized'])
            <dt class="col-md-2">{{ $group['title'] }}</dt>
            <dd class="col-md-10 mb-0">
                @foreach ($group['members'] as $member)
                    <div @if (! $loop->last) class="mb-1" @endif>
                        {{ $member['locale_label'] }}：{{ $member['value'] ?? '' }}
                    </div>
                @endforeach
            </dd>
        @else
            <dt class="col-md-2">{{ $group['title'] }}</dt>
            <dd class="col-md-10 mb-0">{{ $group['members'][0]['value'] ?? '' }}</dd>
        @endif
    @empty
        <span>目前沒有任何 Property</span>
    @endforelse
</dl>
