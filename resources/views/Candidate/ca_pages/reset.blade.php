@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">Đặt lại mật khẩu</h2>
                    <form method="post" action="{{ route('password.update') }}" class="p-4 border rounded">
                        @csrf
                        <input type="hidden" name="token" value="{{ request()->route('token') }}">
                        <div class="form-group">
                            <label for="reset-email">Email</label>
                            <input id="reset-email" type="email" name="email" class="form-control" value="{{ old('email', is_string(request('email')) ? request('email') : '') }}" maxlength="255" autocomplete="email" required>
                        </div>
                        @include('Candidate.password', ['passwordId' => 'reset-password', 'passwordName' => 'password', 'passwordLabel' => 'Mật khẩu mới (ít nhất 8 ký tự, có chữ và số)', 'passwordAutocomplete' => 'new-password'])
                        @include('Candidate.password', ['passwordId' => 'reset-confirmation', 'passwordName' => 'password_confirmation', 'passwordLabel' => 'Xác nhận mật khẩu mới', 'passwordAutocomplete' => 'new-password'])
                        <button type="submit" class="btn btn-primary">Đặt lại mật khẩu</button>
                    </form>
                    <a href="{{ route('login') }}" class="d-block mt-3">Quay lại đăng nhập</a>
                </div>
            </div>
        </div>
    </section>
@endsection
