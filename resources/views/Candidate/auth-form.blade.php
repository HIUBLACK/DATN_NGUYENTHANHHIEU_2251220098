@if (in_array($mode, ['forgot', 'reset']))
    <div class="p-4 border rounded">
        <p>Khôi phục mật khẩu qua email chưa được bật. Chưa có email khôi phục nào được gửi.</p>
        <a href="{{ route('login') }}" class="btn btn-primary">Quay lại đăng nhập</a>
    </div>
@else
    <form action="{{ route($mode === 'settings' ? 'password.change' : 'auth.'.$mode) }}" method="post" class="p-4 border rounded">
        @csrf
        @if ($mode === 'register')
            <div class="form-group mb-3">
                <label class="text-black" for="name-register">Họ tên</label>
                <input type="text" class="form-control" name="name" id="name-register" value="{{ old('name') }}" maxlength="255" autocomplete="name" required>
            </div>
            <div class="form-group mb-3">
                <label class="text-black" for="role-register">Loại tài khoản</label>
                <select class="form-control" name="role" id="role-register" required>
                    <option value="candidate" @selected(old('role') === 'candidate')>Ứng viên</option>
                    <option value="recruiter" @selected(old('role') === 'recruiter')>Nhà tuyển dụng</option>
                </select>
            </div>
        @endif
        @if (in_array($mode, ['login', 'register']))
            <div class="form-group mb-3">
                <label class="text-black" for="email-{{ $mode }}">Email</label>
                <input type="email" class="form-control" name="email" id="email-{{ $mode }}" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
            </div>
        @endif
        @if ($mode === 'settings')
            @include('Candidate.password', ['passwordId' => 'current-password', 'passwordName' => 'current_password', 'passwordLabel' => 'Mật khẩu hiện tại', 'passwordAutocomplete' => 'current-password'])
        @endif
        @include('Candidate.password', ['passwordId' => 'password-'.$mode, 'passwordName' => 'password', 'passwordLabel' => $mode === 'login' ? 'Mật khẩu' : 'Mật khẩu mới (ít nhất 8 ký tự, có chữ và số)', 'passwordAutocomplete' => $mode === 'login' ? 'current-password' : 'new-password'])
        @if (in_array($mode, ['register', 'settings']))
            @include('Candidate.password', ['passwordId' => 'confirm-'.$mode, 'passwordName' => 'password_confirmation', 'passwordLabel' => 'Xác nhận mật khẩu', 'passwordAutocomplete' => 'new-password'])
        @endif
        <button class="btn px-4 btn-primary text-white" type="submit">{{ $buttonLabel }}</button>
        @if ($mode === 'settings')
            <p class="small mt-3 mb-0">Sau khi đổi mật khẩu, bạn sẽ đăng nhập lại trên tất cả thiết bị.</p>
        @endif
    </form>
@endif
