@extends('layouts.app')

@php
    $isEditMode = isset($vertexProperty);
    $methodText = $isEditMode ? '編輯' : '新增';
    $agePropertyNameLocked = $agePropertyNameLocked ?? false;
@endphp

@section('title', $methodText . ' Vertex Property')

@section('content')
    <div class="container">
        <h1>{{ $methodText }} Vertex Property</h1>
        <div class="card">
            <div class="card-body">
                <form role="form" method="POST"
                      action="{{ $isEditMode ? route('graph-schema.vertex-property.update', [$vertexType, $vertexProperty]) : route('graph-schema.vertex-property.store', [$vertexType]) }}">
                    @if($isEditMode)
                        @method('patch')
                    @endif
                    @csrf

                    <x-forms.input id="name" label="名稱" :value="$vertexProperty->name ?? ''" required />
                    <x-forms.input id="description" label="描述" :value="$vertexProperty->description ?? ''" />

                    @include('graph-schema.partials.property-locale-fields', [
                        'property' => $vertexProperty ?? null,
                        'isEditMode' => $isEditMode,
                        'agePropertyNameLocked' => $agePropertyNameLocked,
                    ])

                    @if ($agePropertyNameLocked)
                        <fieldset disabled>
                            <x-forms.select
                                id="age_property_type"
                                label="Property Type"
                                :value="$vertexProperty->age_property_type->value"
                                :options="\App\Enums\PropertyType::selectOptions()"
                                required
                            />
                        </fieldset>
                        <input type="hidden" name="age_property_type" value="{{ $vertexProperty->age_property_type->value }}">
                    @else
                        <x-forms.select
                            id="age_property_type"
                            label="Property Type"
                            :value="$vertexProperty->age_property_type->value ?? ''"
                            :options="\App\Enums\PropertyType::selectOptions()"
                            required
                        />
                    @endif

                    @include('graph-schema.partials.property-enum-options-fields', [
                        'property' => $vertexProperty ?? null,
                        'isEditMode' => $isEditMode,
                        'usedEnumValues' => $usedEnumValues ?? [],
                    ])

                    <div class="row mb-2">
                        <div class="col-md-10 ms-auto">
                            <button type="submit" class="btn btn-primary">儲存</button>
                            <a href="{{ route('graph-schema.vertex-type.show', [$vertexType]) }}" class="btn btn-secondary">返回</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
