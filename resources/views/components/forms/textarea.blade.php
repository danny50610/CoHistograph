<div class="row mb-3">
    <label for="{{ $id }}" class="col-md-2 col-form-label">
        {{ $label }}@if (!empty($required))<span class="text-danger">*</span>@endif
    </label>
    <div class="col-md-10">
        <textarea
            id="{{ $id }}"
            name="{{ $id }}"
            class="form-control @if ($errors->has($id)) is-invalid @endif"
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            @if (!empty($required)) required @endif
            @if (!is_null($helpText)) aria-describedby="{{ $id }}helpBlock" @endif
        >{{ old($id) }}</textarea>
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
