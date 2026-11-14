@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <p class="alert alert-info">Trang doanh nghiệp minh họa. Hồ sơ và ảnh vừa chỉnh sửa chỉ hiển thị trong phiên demo của bạn.</p>
            @include('shared.company-profile', ['profile' => $companyProfile])
            <section class="mt-5" aria-labelledby="company-open-jobs-title">
                <h2 class="h4 mb-4" id="company-open-jobs-title">Tin tuyển dụng đang mở ({{ $companyJobs->count() }})</h2>
                @if ($companyJobs->isEmpty())
                    <p>Chưa có tin tuyển dụng công khai liên kết với hồ sơ này.</p>
                @else
                    <div class="jobs-result-grid">
                        @foreach ($companyJobs as $job)
                            @include('Candidate.search-job-card')
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
