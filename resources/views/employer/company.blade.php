@extends('employer.layout')

@section('content')
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('recruiter.company-create') }}" class="btn btn-light">
            <i class="bi bi-plus-circle" aria-hidden="true"></i>
            Tạo hồ sơ mới
        </a>
        <a href="{{ route('recruiter.company-edit') }}" class="btn-dark-custom">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>
            Chỉnh sửa doanh nghiệp
        </a>
    </div>
    <div class="card p-4 mb-4" id="company-details"></div>
    <h2 class="h5 mb-3">Tin tuyển dụng đang mở</h2>
    <div class="row g-3" id="company-open-jobs"></div>
@endsection
