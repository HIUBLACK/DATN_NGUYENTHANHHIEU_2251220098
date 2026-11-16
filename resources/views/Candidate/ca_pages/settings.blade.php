@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-5">
                    <h2 class="h4 mb-4">Đổi mật khẩu</h2>
                    @include('Candidate.auth-form', ['mode' => 'settings', 'buttonLabel' => 'Đổi mật khẩu'])
                    @include('auth.google-link')
                </div>
                <div class="col-lg-6">
                    <div class="bg-light p-4 border rounded">
                        <h2 class="h4">Thông tin tài khoản</h2>
                        <p>{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
                        <p>Vai trò: Ứng viên. Các mục CV và ứng tuyển vẫn là dữ liệu demo.</p>
                        <form action="{{ route('logout') }}" method="post" class="mb-3">
                            @csrf
                            <button class="btn btn-light">Đăng xuất</button>
                        </form>
                        <form action="{{ route('demo.reset') }}" method="post" data-confirm="Xóa toàn bộ PDF, ảnh và dữ liệu của phiên demo này?">
                            @csrf
                            <button class="btn btn-outline-danger">Xóa dữ liệu demo</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
