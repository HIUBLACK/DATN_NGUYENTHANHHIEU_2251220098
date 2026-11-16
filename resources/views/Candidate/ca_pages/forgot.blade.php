@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    @include('Candidate.auth-form', ['mode' => 'forgot', 'buttonLabel' => 'Gửi liên kết khôi phục'])
                    <p class="mt-3">
                        <a href="{{ route('login') }}">Đăng nhập</a>
                        ·
                        <a href="{{ route('reset') }}">Xem mẫu đặt lại mật khẩu</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
