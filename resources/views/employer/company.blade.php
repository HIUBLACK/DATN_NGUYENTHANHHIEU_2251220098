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
    @if ($companyRecord['status'] === 'active')
        <a href="{{ route('companies.show', $companyRecord['id']) }}" class="btn btn-outline-dark mb-4">
            <i class="bi bi-eye" aria-hidden="true"></i>
            Xem trang doanh nghiệp phía Candidate
        </a>
        @include('shared.company-profile', ['profile' => $companyProfile])
        <div class="card p-4 my-4">
            <h2 class="h5">Liên hệ quản lý — không công khai</h2>
            <p>{{ $companyRecord['email'] }} · {{ $companyRecord['phone'] }}</p>
        </div>
    @else
        <div class="alert alert-warning">Doanh nghiệp demo đang bị khóa, không hiển thị công khai hoặc nhận hồ sơ mới.</div>
    @endif
    <h2 class="h5 mb-3">Tin tuyển dụng minh họa trong phiên Recruiter</h2>
    <div class="row g-3" id="company-open-jobs"></div>
@endsection
