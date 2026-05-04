<div class="row mb-3">
    <label for="{{ $id }}" class="col-md-2 col-form-label">
        {{ $label }}@if (!empty($required))<span class="text-danger">*</span>@endif
    </label>
    <div class="col-md-10">
        <input type="{{ $type }}" class="form-control @if ($errors->has($name ?? $id)) is-invalid @endif "
               id="{{ $id }}" name="{{ $name ?? $id }}" placeholder="{{ $placeholder }}"
               @if (!empty($required)) required @endif
               @if (!empty($readonly)) readonly @endif
               @if (!is_null($helpText)) aria-describedby="{{ $id }}helpBlock"  @endif
               @if (!is_null($autocomplete)) autocomplete="{{ $autocomplete }}"  @endif
               @if (!is_null($inputMode)) inputmode="{{ $inputMode }}"  @endif
               value="{{ old($name ?? $id, $value) }}"
               {{ $attributes }}
               >
        @if (!is_null($helpText))
        <div id="{{ $id }}helpBlock" class="form-text">
            {{ $helpText }}
        </div>
        @endif
        @if ($errors->has($name ?? $id))
            <div class="invalid-feedback">
                @foreach ($errors->get($name ?? $id) as $message)
                    {{ $message }}
                @endforeach
            </div>
        @endif
    </div>
</div>
