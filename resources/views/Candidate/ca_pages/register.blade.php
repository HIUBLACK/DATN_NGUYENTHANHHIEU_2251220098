@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">Đăng ký tài khoản</h2>
                    @include('Candidate.auth-form', ['mode' => 'register', 'buttonLabel' => 'Đăng ký'])
                    @include('Candidate.google-button', ['googleButtonLabel' => 'Đăng ký với Google'])
                    <p class="mt-3">
                        Đã có tài khoản?
                        <a href="{{ route('login') }}">Quay lại đăng nhập</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
