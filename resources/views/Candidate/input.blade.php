<div class="form-group">
    <label class="text-black" for="{{ $fieldName }}">{{ $fieldLabel }}</label>
    <input
        type="{{ $fieldType ?? 'text' }}"
        id="{{ $fieldName }}"
        name="{{ $fieldName }}"
        class="form-control @error($fieldName) is-invalid @enderror"
        value="{{ old($fieldName, $fieldValue ?? '') }}"
        @if ($required ?? false) required @endif
        @if (isset($maxLength)) maxlength="{{ $maxLength }}" @endif
    >
    @error($fieldName)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
