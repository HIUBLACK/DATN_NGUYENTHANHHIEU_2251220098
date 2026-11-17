@extends('employer.layout')

@section('content')
    <div class="d-flex flex-wrap gap-2 mb-4">
        @if (! $companyRecord)
            <a href="{{ route('recruiter.company-create') }}" class="btn btn-outline-dark">
                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                Tạo hồ sơ mới
            </a>
        @elseif ($canSaveCompany)
            <a href="{{ route('recruiter.company-edit') }}" class="btn-dark-custom">
                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                Chỉnh sửa doanh nghiệp
            </a>
        @endif
    </div>
    @if ($companyRecord)
        @if ($companyRecord['status'] === 'active')
            <a href="{{ route('companies.show', $companyRecord['id']) }}" class="btn btn-outline-dark mb-4">
                <i class="bi bi-eye" aria-hidden="true"></i>
                Xem trang doanh nghiệp phía Candidate
            </a>
        @else
            <div class="alert alert-warning">Doanh nghiệp đang bị khóa. Hồ sơ không hiển thị công khai và không thể chỉnh sửa.</div>
        @endif
        @include('shared.company-profile', ['profile' => $companyProfile])
        <div class="card p-4 my-4">
            <h2 class="h5">Liên hệ quản lý — không công khai</h2>
            <p>{{ $companyRecord['email'] }} · {{ $companyRecord['phone'] }}</p>
        </div>
    @else
        <div class="card p-4">
            <h2 class="h5">Bạn chưa có hồ sơ doanh nghiệp</h2>
            <p class="mb-0">Tạo hồ sơ để giới thiệu doanh nghiệp của bạn với ứng viên.</p>
        </div>
    @endif
@endsection
