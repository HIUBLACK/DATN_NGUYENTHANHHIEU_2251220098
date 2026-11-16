@extends('Admin.layout.admin')

@section('content')
    <div class="card p-4">
        <h2 class="h5">Tài khoản quản trị viên</h2>
        <p>{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
        <p>Tài khoản đã đăng nhập. Các thao tác nghiệp vụ trên trang hiện vẫn dùng dữ liệu demo.</p>
        @include('auth.password-errors')
        @include('Candidate.auth-form', ['mode' => 'settings', 'buttonLabel' => 'Đổi mật khẩu'])
        @include('auth.google-link')
        <div class="d-flex flex-wrap gap-2 mt-3">
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit" class="btn btn-outline-dark">Đăng xuất</button>
            </form>
            <button type="button" id="admin-reset" class="btn btn-outline-danger">Đặt lại dữ liệu nghiệp vụ demo</button>
        </div>
    </div>
@endsection
