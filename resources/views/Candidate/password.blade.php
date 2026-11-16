<div class="form-group mb-3">
    <label class="text-black" for="{{ $passwordId }}">{{ $passwordLabel }}</label>
    <div class="input-group">
        <input
            type="password"
            class="form-control"
            id="{{ $passwordId }}"
            name="{{ $passwordName }}"
            minlength="8"
            autocomplete="{{ $passwordAutocomplete ?? 'new-password' }}"
            required
        >
        <div class="input-group-append">
            <button type="button" class="btn btn-light" data-password-toggle="{{ $passwordId }}" aria-pressed="false">Hiện</button>
        </div>
    </div>
</div>
