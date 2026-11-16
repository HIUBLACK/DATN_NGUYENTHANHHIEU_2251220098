@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">Đăng nhập VIEJOB AI</h2>
                    @include('Candidate.auth-form', ['mode' => 'login', 'buttonLabel' => 'Đăng nhập'])
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
