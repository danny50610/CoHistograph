@php
    $localeValue = old('locale', $property->locale ?? '');
    $isLocalized = $localeValue !== '' && $localeValue !== null;
    $locales = config('cohistograph.app.graph.locales', []);
    $agePropertyNameLocked = $agePropertyNameLocked ?? false;
    $baseAgePropertyName = old(
        'base_age_property_name',
        ($isEditMode && isset($property) && $property->locale)
            ? \App\Support\LocalizedPropertyName::baseName($property)
            : ''
    );
    $agePropertyName = old(
        'age_property_name',
        ($isEditMode && isset($property) && $property->locale === null)
            ? $property->age_property_name
            : ''
    );
@endphp

@if ($isEditMode && isset($property) && $agePropertyNameLocked)
    <div class="row mb-3">
        <label class="col-md-2 col-form-label">語言版本</label>
        <div class="col-md-10 col-form-label">
            @if ($property->locale)
                {{ $locales[$property->locale] ?? $property->locale }}
                <span class="text-body-secondary">({{ $property->locale }})</span>
            @else
                非多語系
            @endif
        </div>
    </div>

    <div class="row mb-3">
        <label class="col-md-2 col-form-label">Property 名稱</label>
        <div class="col-md-10 col-form-label">
            {{ $property->age_property_name }}
            <div class="form-text">圖資料庫中已有此屬性的資料，無法變更 Property 名稱</div>
        </div>
    </div>
@elseif ($isEditMode && isset($property))
    <div class="row mb-3">
        <label class="col-md-2 col-form-label">語言版本</label>
        <div class="col-md-10 col-form-label">
            @if ($property->locale)
                {{ $locales[$property->locale] ?? $property->locale }}
                <span class="text-body-secondary">({{ $property->locale }})</span>
            @else
                非多語系
            @endif
        </div>
    </div>

    @if ($property->locale)
        <x-forms.input
            id="base_age_property_name"
            label="AGE 屬性基底名稱"
            :value="$baseAgePropertyName"
            required
        />
        @if ($errors->has('resolved_age_property_name'))
            <div class="row mb-3">
                <div class="col-md-10 offset-md-2">
                    <div class="text-danger small">
                        @foreach ($errors->get('resolved_age_property_name') as $message)
                            {{ $message }}
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
        <div class="row mb-3">
            <div class="col-md-10 offset-md-2">
                <div class="form-text text-body-secondary">
                    → 將儲存為 {{ $baseAgePropertyName !== '' ? $baseAgePropertyName.'_'.$property->locale : '…' }}
                </div>
            </div>
        </div>
    @else
        <x-forms.input
            id="age_property_name"
            label="Property 名稱"
            :value="$agePropertyName"
            required
        />
        @if ($errors->has('resolved_age_property_name'))
            <div class="row mb-3">
                <div class="col-md-10 offset-md-2">
                    <div class="text-danger small">
                        @foreach ($errors->get('resolved_age_property_name') as $message)
                            {{ $message }}
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif
@else
    <div class="row mb-3">
        <label for="locale" class="col-md-2 col-form-label">語言版本</label>
        <div class="col-md-10">
            <select id="locale" name="locale" class="form-select @if ($errors->has('locale')) is-invalid @endif">
                <option value="" @selected(! $isLocalized)>非多語系</option>
                @foreach ($locales as $localeKey => $localeLabel)
                    <option value="{{ $localeKey }}" @selected($localeValue === $localeKey)>
                        {{ $localeLabel }}（{{ $localeKey }}）
                    </option>
                @endforeach
            </select>
            @if ($errors->has('locale'))
                <div class="invalid-feedback">
                    @foreach ($errors->get('locale') as $message)
                        {{ $message }}
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div id="age-property-name-block" @if ($isLocalized) style="display: none;" @endif>
        <x-forms.input
            id="age_property_name"
            label="Property 名稱"
            :value="old('age_property_name', '')"
            :required="! $isLocalized"
        />
        @if ($errors->has('resolved_age_property_name') && ! $isLocalized)
            <div class="row mb-3">
                <div class="col-md-10 offset-md-2">
                    <div class="text-danger small">
                        @foreach ($errors->get('resolved_age_property_name') as $message)
                            {{ $message }}
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div id="base-age-property-name-block" @if (! $isLocalized) style="display: none;" @endif>
        <x-forms.input
            id="base_age_property_name"
            label="AGE 屬性基底名稱"
            :value="old('base_age_property_name', '')"
            :required="$isLocalized"
        />
        @if ($errors->has('resolved_age_property_name') && $isLocalized)
            <div class="row mb-3">
                <div class="col-md-10 offset-md-2">
                    <div class="text-danger small">
                        @foreach ($errors->get('resolved_age_property_name') as $message)
                            {{ $message }}
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
        <div class="row mb-3">
            <div class="col-md-10 offset-md-2">
                <div id="resolved-age-property-name-preview" class="form-text text-body-secondary">
                    @if ($isLocalized && old('base_age_property_name'))
                        → 將儲存為 {{ old('base_age_property_name') }}_{{ $localeValue }}
                    @else
                        → 將儲存為 …
                    @endif
                </div>
            </div>
        </div>
    </div>

    @once
        @push('js')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const localeSelect = document.getElementById('locale');
                    const agePropertyNameBlock = document.getElementById('age-property-name-block');
                    const basePropertyNameBlock = document.getElementById('base-age-property-name-block');
                    const preview = document.getElementById('resolved-age-property-name-preview');
                    const baseInput = document.getElementById('base_age_property_name');
                    const ageInput = document.getElementById('age_property_name');

                    if (! localeSelect || ! agePropertyNameBlock || ! basePropertyNameBlock) {
                        return;
                    }

                    function updatePreview() {
                        const locale = localeSelect.value;
                        const base = baseInput?.value ?? '';

                        if (! preview) {
                            return;
                        }

                        if (! locale) {
                            preview.textContent = '→ 將儲存為 …';

                            return;
                        }

                        preview.textContent = base
                            ? `→ 將儲存為 ${base}_${locale}`
                            : '→ 將儲存為 …';
                    }

                    function updateVisibility() {
                        const isLocalized = localeSelect.value !== '';

                        agePropertyNameBlock.style.display = isLocalized ? 'none' : '';
                        basePropertyNameBlock.style.display = isLocalized ? '' : 'none';

                        if (ageInput) {
                            ageInput.required = ! isLocalized;
                            ageInput.disabled = isLocalized;
                        }

                        if (baseInput) {
                            baseInput.required = isLocalized;
                            baseInput.disabled = ! isLocalized;
                        }

                        updatePreview();
                    }

                    localeSelect.addEventListener('change', updateVisibility);
                    baseInput?.addEventListener('input', updatePreview);
                    updateVisibility();
                });
            </script>
        @endpush
    @endonce
@endif
