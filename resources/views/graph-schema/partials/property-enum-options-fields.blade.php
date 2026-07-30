@php
    $persistedEnumOptions = $property->enum_options ?? [];
    $persistedEnumValues = array_column($persistedEnumOptions, 'value');
    $enumOptions = old('enum_options', $persistedEnumOptions);
    $enumOptions = is_array($enumOptions) ? array_values($enumOptions) : [];
    $usedEnumValues = $usedEnumValues ?? [];
    $propertyType = old('age_property_type', $property->age_property_type->value ?? '');
    $isEnum = $propertyType === \App\Enums\PropertyType::Enum->value;
    $minSelections = old('min_selections', $property?->min_selections ?? \App\Support\EnumOptions::DEFAULT_MIN_SELECTIONS);
    $maxSelections = old('max_selections', $property?->max_selections);
@endphp

<fieldset id="enum-options-fields" class="row mb-3" @if (! $isEnum) hidden disabled @endif>
    <legend class="col-md-2 col-form-label pt-0">ENUM 選項</legend>
    <div class="col-md-10">
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <label class="form-label" for="min_selections">最少選取數</label>
                <input
                    id="min_selections"
                    type="number"
                    name="min_selections"
                    value="{{ $minSelections }}"
                    class="form-control @if ($errors->has('min_selections')) is-invalid @endif"
                    min="1"
                    max="255"
                    required
                >
                @if ($errors->has('min_selections'))
                    <div class="invalid-feedback">
                        @foreach ($errors->get('min_selections') as $message)
                            {{ $message }}
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label" for="max_selections">最多選取數</label>
                <input
                    id="max_selections"
                    type="number"
                    name="max_selections"
                    value="{{ $maxSelections }}"
                    class="form-control @if ($errors->has('max_selections')) is-invalid @endif"
                    min="1"
                    max="255"
                    placeholder="不限"
                >
                @if ($errors->has('max_selections'))
                    <div class="invalid-feedback">
                        @foreach ($errors->get('max_selections') as $message)
                            {{ $message }}
                        @endforeach
                    </div>
                @else
                    <div class="form-text">留空＝不限上限</div>
                @endif
            </div>
        </div>

        <div class="row g-2 mb-1 d-none d-md-flex text-body-secondary small">
            <div class="col-md-3">Value</div>
            <div class="col-md-4">Label</div>
            <div class="col-md-2">啟用</div>
            <div class="col-md-3">操作</div>
        </div>

        <div id="enum-options-rows">
            @foreach ($enumOptions as $index => $option)
                @php
                    $optionValue = (string) ($option['value'] ?? '');
                    $isPersisted = $isEditMode && in_array($optionValue, $persistedEnumValues, true);
                    $isUsed = in_array($optionValue, $usedEnumValues, true);
                    $isActive = filter_var($option['active'] ?? true, FILTER_VALIDATE_BOOLEAN);
                @endphp
                <div class="row g-2 align-items-start mb-2 enum-option-row">
                    <div class="col-md-3">
                        <label class="visually-hidden" for="enum-option-value-{{ $index }}">Value</label>
                        <input
                            id="enum-option-value-{{ $index }}"
                            type="text"
                            name="enum_options[{{ $index }}][value]"
                            value="{{ $optionValue }}"
                            class="form-control enum-option-value @if ($errors->has("enum_options.$index.value")) is-invalid @endif"
                            pattern="[a-z0-9_+\-]{1,64}"
                            maxlength="64"
                            title="僅限小寫 a-z、0-9、底線、加號與減號，共 1–64 字元"
                            required
                            @readonly($isPersisted)
                        >
                        @if ($errors->has("enum_options.$index.value"))
                            <div class="invalid-feedback">
                                @foreach ($errors->get("enum_options.$index.value") as $message)
                                    {{ $message }}
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="visually-hidden" for="enum-option-label-{{ $index }}">Label</label>
                        <input
                            id="enum-option-label-{{ $index }}"
                            type="text"
                            name="enum_options[{{ $index }}][label]"
                            value="{{ $option['label'] ?? '' }}"
                            class="form-control enum-option-label @if ($errors->has("enum_options.$index.label")) is-invalid @endif"
                            maxlength="128"
                            required
                        >
                        @if ($errors->has("enum_options.$index.label"))
                            <div class="invalid-feedback">
                                @foreach ($errors->get("enum_options.$index.label") as $message)
                                    {{ $message }}
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="col-md-2 pt-md-2">
                        <input type="hidden" name="enum_options[{{ $index }}][active]" value="0">
                        <div class="form-check">
                            <input
                                id="enum-option-active-{{ $index }}"
                                type="checkbox"
                                name="enum_options[{{ $index }}][active]"
                                value="1"
                                class="form-check-input enum-option-active"
                                @checked($isActive)
                            >
                            <label class="form-check-label" for="enum-option-active-{{ $index }}">啟用</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm move-enum-option-up" title="上移">
                            <i class="fa-solid fa-arrow-up"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm move-enum-option-down" title="下移">
                            <i class="fa-solid fa-arrow-down"></i>
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-danger btn-sm remove-enum-option"
                            @disabled($isUsed)
                            @if ($isUsed) title="此選項已有資料使用，無法刪除" @else title="刪除" @endif
                        >
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($errors->has('enum_options'))
            <div class="text-danger small mb-2">
                @foreach ($errors->get('enum_options') as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        <button type="button" id="add-enum-option" class="btn btn-outline-secondary btn-sm">新增選項</button>
        <div class="form-text">Value 僅限小寫 a-z、0-9、底線（_）、加號（+）與減號（-），共 1–64 字元。啟用選項數須 ≥ 最少選取數。</div>
    </div>
</fieldset>

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeSelect = document.getElementById('age_property_type');
            const localeSelect = document.getElementById('locale');
            const enumFields = document.getElementById('enum-options-fields');
            const rowsContainer = document.getElementById('enum-options-rows');
            const addButton = document.getElementById('add-enum-option');

            if (! typeSelect || ! enumFields || ! rowsContainer || ! addButton) {
                return;
            }

            function reindexRows() {
                const rows = [...rowsContainer.querySelectorAll('.enum-option-row')];

                rows.forEach((row, index) => {
                    const valueInput = row.querySelector('.enum-option-value');
                    const labelInput = row.querySelector('.enum-option-label');
                    const activeInput = row.querySelector('.enum-option-active');
                    const hiddenActiveInput = row.querySelector('input[type="hidden"]');

                    valueInput.name = `enum_options[${index}][value]`;
                    valueInput.id = `enum-option-value-${index}`;
                    row.querySelector('label[for^="enum-option-value-"]').htmlFor = valueInput.id;

                    labelInput.name = `enum_options[${index}][label]`;
                    labelInput.id = `enum-option-label-${index}`;
                    row.querySelector('label[for^="enum-option-label-"]').htmlFor = labelInput.id;

                    hiddenActiveInput.name = `enum_options[${index}][active]`;
                    activeInput.name = `enum_options[${index}][active]`;
                    activeInput.id = `enum-option-active-${index}`;
                    row.querySelector('.form-check-label').htmlFor = activeInput.id;

                    row.querySelector('.move-enum-option-up').disabled = index === 0;
                    row.querySelector('.move-enum-option-down').disabled = index === rows.length - 1;
                });
            }

            function createRow() {
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-start mb-2 enum-option-row';
                row.innerHTML = `
                    <div class="col-md-3">
                        <label class="visually-hidden" for="enum-option-value-new">Value</label>
                        <input id="enum-option-value-new" type="text" class="form-control enum-option-value"
                            pattern="[a-z0-9_+\\-]{1,64}" maxlength="64"
                            title="僅限小寫 a-z、0-9、底線、加號與減號，共 1–64 字元" required>
                    </div>
                    <div class="col-md-4">
                        <label class="visually-hidden" for="enum-option-label-new">Label</label>
                        <input id="enum-option-label-new" type="text" class="form-control enum-option-label"
                            maxlength="128" required>
                    </div>
                    <div class="col-md-2 pt-md-2">
                        <input type="hidden" value="0">
                        <div class="form-check">
                            <input id="enum-option-active-new" type="checkbox"
                                value="1" class="form-check-input enum-option-active" checked>
                            <label class="form-check-label" for="enum-option-active-new">啟用</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm move-enum-option-up" title="上移">
                            <i class="fa-solid fa-arrow-up"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm move-enum-option-down" title="下移">
                            <i class="fa-solid fa-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm remove-enum-option" title="刪除">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                `;

                return row;
            }

            function updateTypeFields() {
                const isEnum = typeSelect.value === 'ENUM';

                enumFields.hidden = ! isEnum;
                enumFields.disabled = ! isEnum;

                if (! localeSelect) {
                    return;
                }

                if (isEnum) {
                    localeSelect.value = '';
                    localeSelect.dispatchEvent(new Event('change'));
                    localeSelect.disabled = true;
                } else {
                    localeSelect.disabled = false;
                }
            }

            addButton.addEventListener('click', function () {
                rowsContainer.appendChild(createRow());
                reindexRows();
                rowsContainer.lastElementChild.querySelector('.enum-option-value').focus();
            });

            rowsContainer.addEventListener('click', function (event) {
                const button = event.target.closest('button');

                if (! button) {
                    return;
                }

                const row = button.closest('.enum-option-row');

                if (button.classList.contains('remove-enum-option')) {
                    row.remove();
                } else if (button.classList.contains('move-enum-option-up') && row.previousElementSibling) {
                    rowsContainer.insertBefore(row, row.previousElementSibling);
                } else if (button.classList.contains('move-enum-option-down') && row.nextElementSibling) {
                    rowsContainer.insertBefore(row.nextElementSibling, row);
                }

                reindexRows();
            });

            typeSelect.addEventListener('change', updateTypeFields);
            reindexRows();
            updateTypeFields();
        });
    </script>
@endpush
