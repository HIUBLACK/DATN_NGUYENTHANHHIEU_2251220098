@extends('employer.layout')

@section('content')
    <div class="card p-4">
        <h2 class="h5">Tài khoản nhà tuyển dụng</h2>
        <p>Đây là không gian xem thử, chưa có phiên đăng nhập nhà tuyển dụng thật. Chức năng tài khoản và đăng xuất sẽ kết nối hệ thống xác thực ở giai đoạn Backend.</p>
        <p>Email minh họa: recruiter@example.com</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('login') }}" class="btn-dark-custom">Đến trang đăng nhập hiện có</a>
            <button type="button" class="btn btn-light" data-exit-demo>Đăng xuất — xem thông tin</button>
            <button type="button" id="reset-recruiter-demo" class="btn btn-outline-danger">Đặt lại tin và hồ sơ ứng tuyển demo</button>
        </div>
    </div>
@endsection
