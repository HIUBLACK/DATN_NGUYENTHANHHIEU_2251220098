@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-5">
                    <h2 class="mb-4">Đăng ký ứng viên</h2>
                    @include('Candidate.auth-form', ['mode' => 'register', 'buttonLabel' => 'Đăng ký'])
                </div>
                <div class="col-lg-6">
                    <h2 class="mb-4">Đăng nhập VIEJOB AI</h2>
                    @include('Candidate.auth-form', ['mode' => 'login', 'buttonLabel' => 'Đăng nhập'])
                    <a href="{{ route('forgot') }}" class="d-inline-block mt-3">Quên mật khẩu?</a>
                    <form action="{{ route('demo.enter') }}" method="post" class="mt-4">
                        @csrf
                        <button class="btn btn-outline-primary">Xem thử giao diện đã đăng nhập</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
