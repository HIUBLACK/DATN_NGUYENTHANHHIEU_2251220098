@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    @include('Candidate.auth-form', ['mode' => 'reset', 'buttonLabel' => 'Đặt lại mật khẩu'])
                    <a href="{{ route('login') }}" class="d-block mt-3">Quay lại đăng nhập</a>
                </div>
            </div>
        </div>
    </section>
@endsection
