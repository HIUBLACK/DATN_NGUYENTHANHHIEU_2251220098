<div class="mt-3">
    @if (\App\Services\GoogleAuthService::configured())
        <a href="{{ route('google.redirect', ['context' => $page === 'register' ? 'register' : 'login']) }}" class="btn btn-outline-primary btn-block">
            {{ $googleButtonLabel }}
        </a>
    @else
        <button type="button" class="btn btn-outline-primary btn-block" disabled aria-describedby="google-availability">
            {{ $googleButtonLabel }}
        </button>
        <p id="google-availability" class="small text-muted mt-2 mb-0">
            Đăng nhập bằng Google chưa được cấu hình. Vui lòng sử dụng email và mật khẩu.
        </p>
    @endif
</div>
