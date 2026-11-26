@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">Đăng nhập VIEJOB AI</h2>
                    @include('Candidate.auth-form', ['mode' => 'login', 'buttonLabel' => 'Đăng nhập'])
                    @if (\App\Services\PasswordRecoveryService::available())
                        <a href="{{ route('password.request') }}" class="d-inline-block mt-3">Quên mật khẩu?</a>
                    @endif
                    @include('Candidate.google-button', ['googleButtonLabel' => 'Tiếp tục với Google'])
                    <p class="mt-3">
                        Chưa có tài khoản?
                        <a href="{{ route('register') }}">Đăng ký tài khoản</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
