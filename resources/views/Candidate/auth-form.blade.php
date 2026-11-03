<form action="{{ route('demo.auth', $mode) }}" method="post" class="p-4 border rounded">
    @csrf
    @if (in_array($mode, ['login', 'register', 'forgot']))
        <div class="form-group">
            <label class="text-black" for="email-{{ $mode }}">Email</label>
            <input type="email" class="form-control" name="email" id="email-{{ $mode }}" autocomplete="email" required>
        </div>
    @endif
    @if ($mode === 'settings')
        @include('Candidate.password', ['passwordId' => 'current-password', 'passwordName' => 'current_password', 'passwordLabel' => 'Mật khẩu hiện tại'])
    @endif
    @if ($mode !== 'forgot')
        @include('Candidate.password', ['passwordId' => 'password-'.$mode, 'passwordName' => 'password', 'passwordLabel' => 'Mật khẩu (ít nhất 8 ký tự)'])
    @endif
    @if (in_array($mode, ['register', 'reset', 'settings']))
        @include('Candidate.password', ['passwordId' => 'confirm-'.$mode, 'passwordName' => 'password_confirmation', 'passwordLabel' => 'Xác nhận mật khẩu'])
    @endif
    <button class="btn px-4 btn-primary text-white" type="submit">{{ $buttonLabel }}</button>
    <p class="small mt-3 mb-0">Biểu mẫu minh họa. Không nhập mật khẩu đang sử dụng. Chưa tạo tài khoản hoặc gửi thư thật.</p>
</form>
