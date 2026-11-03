<div class="form-group">
    <label class="text-black" for="{{ $passwordId }}">{{ $passwordLabel }}</label>
    <div class="input-group">
        <input
            type="password"
            class="form-control"
            id="{{ $passwordId }}"
            name="{{ $passwordName }}"
            minlength="8"
            autocomplete="new-password"
            required
        >
        <div class="input-group-append">
            <button type="button" class="btn btn-light" data-password-toggle="{{ $passwordId }}" aria-pressed="false">Hiện</button>
        </div>
    </div>
</div>
