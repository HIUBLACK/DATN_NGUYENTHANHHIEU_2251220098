@extends('Admin.layout.admin')

@section('content')
    <div class="card p-4">
        <h2 class="h4">Admin mẫu 1</h2>
        <p>admin1@example.com · Quản trị viên demo</p>
        <p>Chưa tích hợp phiên đăng nhập Admin. Không thay đổi vai trò hoặc thông tin tài khoản thật tại đây.</p>
        <div class="admin-actions">
            <a href="{{ route('login') }}" class="btn btn-outline-dark">Đến trang đăng nhập hiện có</a>
            <button type="button" class="btn btn-outline-dark" data-admin-logout>Đăng xuất</button>
            <button type="button" class="btn btn-outline-dark" id="admin-reset">Đặt lại dữ liệu Admin demo</button>
        </div>
    </div>
@endsection
