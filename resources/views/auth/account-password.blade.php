@if (\App\Services\GooglePasswordSetupService::eligible(auth()->user()))
    <div class="p-4 border rounded">
        <h3 class="h5">Thiết lập mật khẩu</h3>
        @if (app(\App\Services\GooglePasswordSetupService::class)->hasGrant(request()))
            <p>Đã xác thực lại Google. Tạo mật khẩu để có thể đăng nhập bằng email.</p>
            <form action="{{ route('password.setup.complete') }}" method="post">
                @csrf
                @include('Candidate.password', ['passwordId' => 'setup-password', 'passwordName' => 'password', 'passwordLabel' => 'Mật khẩu mới (ít nhất 8 ký tự, có chữ và số)', 'passwordAutocomplete' => 'new-password'])
                @include('Candidate.password', ['passwordId' => 'setup-password-confirmation', 'passwordName' => 'password_confirmation', 'passwordLabel' => 'Xác nhận mật khẩu mới', 'passwordAutocomplete' => 'new-password'])
                <button type="submit" class="btn btn-primary">Lưu mật khẩu</button>
            </form>
        @else
            <p>Tài khoản đang sử dụng Google. Hãy xác thực lại đúng tài khoản Google trước khi tạo mật khẩu.</p>
            <form action="{{ route('password.setup.google') }}" method="post">
                @csrf
                <button type="submit" class="btn btn-primary" @disabled(! \App\Services\GoogleAuthService::configured())>Xác thực lại Google</button>
            </form>
        @endif
    </div>
@else
    @if (auth()->user()->has_local_password === null)
        <p class="alert alert-info">
            Chưa xác định được phương thức mật khẩu trước đây của tài khoản. Nếu chưa có hoặc không nhớ mật khẩu, hãy đăng xuất và dùng Quên mật khẩu để xác minh qua email.
        </p>
    @endif
    @include('Candidate.auth-form', ['mode' => 'settings', 'buttonLabel' => 'Đổi mật khẩu'])
@endif
