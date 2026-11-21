@extends('Candidate.master')

@section('content')
    <section class="job-detail-page">
        <div class="container">
            <nav class="job-detail-breadcrumb" aria-label="Đường dẫn">
                <a href="{{ route('home') }}">Trang chủ</a>
                <span>/</span>
                <a href="{{ route('jobs') }}">Việc làm</a>
            </nav>
            <div class="row">
                <div class="col-lg-8">
                    <section class="job-detail-card">
                        <span class="badge badge-success">Đang tuyển dụng</span>
                        <h1 class="job-detail-title mt-3">{{ $job['title'] }}</h1>
                        <p class="job-detail-salary">{{ $job['salary_negotiable'] ? 'Lương thỏa thuận' : $job['salary_min'].'–'.$job['salary_max'].' triệu/tháng' }}</p>
                        <p>{{ $job['location'] }} · {{ $job['level'] }} · Hạn {{ $job['deadline']->format('d/m/Y') }}</p>
                        @guest
                            <a href="{{ route('login') }}" class="btn btn-primary text-white">Đăng nhập để ứng tuyển</a>
                        @else
                            @can('viewAny', App\Models\Resume::class)
                                @if (! $applicationEligibility['allowed'])
                                    <p class="alert alert-info">Bạn đã nộp hồ sơ hoặc đã sử dụng đủ lượt ứng tuyển.</p>
                                    <a href="{{ route('candidate.applications') }}">Xem hồ sơ đã nộp</a>
                                @elseif ($applicationResumes->isEmpty())
                                    <a href="{{ route('candidate.resumes') }}" class="btn btn-primary text-white">Tải CV để ứng tuyển</a>
                                @else
                                    <button type="button" class="btn btn-primary text-white" data-toggle="modal" data-target="#apply-real-modal">Ứng tuyển</button>
                                @endif
                            @endcan
                        @endguest
                    </section>
                    <section class="job-detail-card">
                        @foreach (['description' => 'Mô tả công việc', 'requirements' => 'Yêu cầu ứng viên', 'benefits' => 'Quyền lợi'] as $field => $label)
                            <div class="job-detail-section">
                                <h2 class="job-detail-heading">{{ $label }}</h2>
                                <p class="company-profile-text">{{ $job[$field] }}</p>
                            </div>
                        @endforeach
                        <h2 class="job-detail-heading">Kỹ năng yêu cầu</h2>
                        <div class="job-detail-skills">
                            @foreach ($job['skills'] as $skill)
                                <span class="badge badge-light border">{{ $skill }}</span>
                            @endforeach
                        </div>
                    </section>
                </div>
                <aside class="col-lg-4">
                    <section class="job-detail-card">
                        @if ($job['logo_url'])
                            <img src="{{ $job['logo_url'] }}" alt="Logo {{ $job['company'] }}" class="search-job-logo">
                        @endif
                        <h2 class="h4">
                            <a href="{{ $job['company_url'] }}">{{ $job['company'] }}</a>
                        </h2>
                        <p>{{ $job['position'] }} · {{ $job['specialization'] }}</p>
                        <p>{{ $job['mode'] }} · {{ $job['type'] }}</p>
                        <p>Địa điểm chi tiết: {{ $job['location_detail'] }}</p>
                        @if (! empty($job['experience']))
                            <p>Kinh nghiệm: {{ $job['experience'] }}</p>
                        @endif
                        <p>Số lượng tuyển: {{ $job['quantity'] }}</p>
                    </section>
                </aside>
            </div>
        </div>
    </section>
    @if ($applicationEligibility['allowed'] && $applicationResumes->isNotEmpty())
        @include('Candidate.real-apply-modal')
    @endif
@endsection
