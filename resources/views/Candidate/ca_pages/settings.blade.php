@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-5">
                    <h2 class="h4 mb-4">Đổi mật khẩu</h2>
                    @include('Candidate.auth-form', ['mode' => 'settings', 'buttonLabel' => 'Kiểm tra biểu mẫu'])
                </div>
                <div class="col-lg-6">
                    <div class="bg-light p-4 border rounded">
                        <h2 class="h4">Thông tin tài khoản</h2>
                        <p>{{ $demo['profile']['name'] }} · {{ $demo['profile']['email'] }}</p>
                        <p>Vai trò: Ứng viên — chế độ minh họa, chưa xác thực thật.</p>
                        <form action="{{ route('demo.leave') }}" method="post" class="mb-3">
                            @csrf
                            <button class="btn btn-light">Đăng xuất demo</button>
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
