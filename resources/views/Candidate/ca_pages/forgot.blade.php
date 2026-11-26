@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">Quên mật khẩu</h2>
                    @if (\App\Services\PasswordRecoveryService::available())
                        <form method="post" action="{{ route('password.email') }}" class="p-4 border rounded">
                            @csrf
                            <div class="form-group">
                                <label for="recovery-email">Email</label>
                                <input id="recovery-email" type="email" name="email" class="form-control" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Gửi liên kết đặt lại mật khẩu</button>
                        </form>
                    @else
                        <p class="alert alert-info">Khôi phục mật khẩu qua email chưa được cấu hình. Chưa có email khôi phục nào được gửi.</p>
                    @endif
                    <p class="mt-3">
                        <a href="{{ route('login') }}">Đăng nhập</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
