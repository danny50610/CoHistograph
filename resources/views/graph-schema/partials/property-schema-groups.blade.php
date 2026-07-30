@php
    use App\Enums\PropertyType;
    use App\Support\EnumOptions;

    /** @var \Illuminate\Database\Eloquent\Model $typeModel */
    /** @var list<array{title: string, is_localized: bool, members: list<array{property: object, locale_label: string|null, value: mixed}>}> $groups */
@endphp

<dl class="row">
    @forelse ($groups as $group)
        <dt class="col-md-2">
            {{ $group['title'] }}
            @if (! $group['is_localized'] && count($group['members']) === 1)
                <span class="text-body-secondary">({{ $group['members'][0]['property']->age_property_name }})</span>
                <a href="{{ route($propertyShowRoute, [$typeModel, $group['members'][0]['property']]) }}"><i class="fa-solid fa-receipt"></i></a>
            @endif
        </dt>
        <dd class="col-md-10">
            @if ($group['is_localized'])
                @foreach ($group['members'] as $member)
                    @php $property = $member['property']; @endphp
                    <div @if (! $loop->last) class="mb-2" @endif>
                        <span class="text-body-secondary">{{ $member['locale_label'] }}</span>
                        <span class="text-body-secondary">{{ $property->age_property_name }}</span>
                        <a href="{{ route($propertyShowRoute, [$typeModel, $property]) }}"><i class="fa-solid fa-receipt"></i></a>
                        <span class="badge text-bg-info">{{ $property->age_property_type }}</span>
                        @if ($property->age_property_type === PropertyType::Enum)
                            @php $enumLabels = EnumOptions::formatSchemaLabels($property->enum_options); @endphp
                            @if ($enumLabels !== '')
                                <span class="text-body-secondary">{{ $enumLabels }}</span>
                            @endif
                        @endif
                        {{ $property->description }}
                    </div>
                @endforeach
            @else
                @php $property = $group['members'][0]['property']; @endphp
                <span class="badge text-bg-info">{{ $property->age_property_type }}</span>
                @if ($property->age_property_type === PropertyType::Enum)
                    @php $enumLabels = EnumOptions::formatSchemaLabels($property->enum_options); @endphp
                    @if ($enumLabels !== '')
                        <span class="text-body-secondary">{{ $enumLabels }}</span>
                    @endif
                @endif
                {{ $property->description }}
            @endif
        </dd>
    @empty
        <span>目前沒有任何 Property</span>
    @endforelse
</dl>
