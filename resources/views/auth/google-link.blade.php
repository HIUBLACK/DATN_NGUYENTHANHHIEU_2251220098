<div class="border rounded p-4 mt-4">
    <h3 class="h5">Liên kết tài khoản Google</h3>
    @if (session('status'))
        <p class="alert alert-success">{{ session('status') }}</p>
    @endif
    @if (auth()->user()->google_subject)
        <p class="mb-0">Tài khoản đã liên kết với Google. Bạn có thể đăng nhập bằng Google tại trang đăng nhập chung.</p>
    @elseif (\App\Services\GoogleAuthService::configured())
        <p>Xác nhận mật khẩu hiện tại, sau đó chọn tài khoản Google muốn liên kết.</p>
        <form action="{{ route('google.link') }}" method="post">
            @csrf
            @include('Candidate.password', ['passwordId' => 'google-link-password', 'passwordName' => 'current_password', 'passwordLabel' => 'Mật khẩu hiện tại', 'passwordAutocomplete' => 'current-password'])
            <button type="submit" class="btn btn-outline-primary">Liên kết Google</button>
        </form>
    @else
        <p class="mb-0 text-muted">Liên kết Google chưa được cấu hình.</p>
    @endif
</div>
