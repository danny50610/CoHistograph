<div class="row mb-3">
    <label for="{{ $id }}" class="col-md-2 col-form-label">
        {{ $label }}@if (!empty($required))<span class="text-danger">*</span>@endif
    </label>
    <div class="col-md-10">
        <select id="{{ $id }}" name="{{ $id }}" class="form-select @if ($errors->has($id)) is-invalid @endif">
            <option @if (is_null(old($id, $value))) selected @endif" value></option>
            @foreach ($options as $option)
                <option value="{{ $option['value'] }}" @if (old($id, $value) === $option['value']) selected @endif">{{ $option['label'] }}</option>
            @endforeach
        </select>
        @if (!is_null($helpText))
        <div id="{{ $id }}helpBlock" class="form-text">
            {{ $helpText }}
        </div>
        @endif
        @if ($errors->has($id))
            <div class="invalid-feedback">
                @foreach ($errors->get($id) as $message)
                    {{ $message }}
                @endforeach
            </div>
        @endif
    </div>
</div>
