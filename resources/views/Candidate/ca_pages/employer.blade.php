@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="bg-light p-4 border rounded">
                <h2 class="h4 text-primary">Không gian nhà tuyển dụng</h2>
                <p>Giai đoạn hiện tại tập trung vào giao diện công khai và ứng viên. Đăng tin và quản lý tuyển dụng chưa được kết nối.</p>
                <a href="{{ route('home') }}" class="btn btn-primary text-white">Về trang chủ</a>
            </div>
        </div>
    </section>
@endsection
