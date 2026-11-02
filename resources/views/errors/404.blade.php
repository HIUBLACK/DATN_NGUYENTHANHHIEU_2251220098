@extends('layouts.public')
@section('title','Nội dung không khả dụng')
@section('content')<div class="page-container error-page"><strong>404</strong><h1>Nội dung không khả dụng</h1><p>Trang hoặc công việc này không tồn tại hoặc đã được ẩn.</p><a href="{{ route('jobs') }}" class="button">Khám phá việc làm khác ↗</a></div>@endsection
