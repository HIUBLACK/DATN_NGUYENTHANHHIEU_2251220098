<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title }} — VIEJOB Recruiter</title>
        <link rel="icon" href="{{ asset('recruiter/images/favicon.ico') }}">
        <link rel="stylesheet" href="{{ asset('recruiter/libs/bootstrap/css/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('recruiter/libs/bootstrap-icons/bootstrap-icons.css') }}">
        <link rel="stylesheet" href="{{ asset('recruiter/css/main.css') }}">
        <link rel="stylesheet" href="{{ asset('recruiter/css/recruiter.css') }}">
        <link rel="stylesheet" href="{{ asset('css/company-profile.css') }}">
        <link rel="stylesheet" href="{{ asset('recruiter/css/theme.css') }}">
    </head>
    <body data-recruiter-page="{{ $page }}">
        @include('employer.component.sidebar')
        <div class="main-wrapper">
            @include('employer.component.header')
            <div class="page-header">
                <div>
                    <h1 class="page-title">{{ $title }}</h1>
                    <p class="page-subtitle">Không gian làm việc dành cho nhà tuyển dụng</p>
                </div>
                <nav aria-label="Đường dẫn">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('recruiter.dashboard') }}">Tổng quan</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                    </ol>
                </nav>
            </div>
            <main id="recruiter-content">
                <div class="alert alert-light border recruiter-demo-notice" role="note">
                    <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
                    Bản demo giao diện. Thao tác trong phiên thử nghiệm, chưa lưu database, gửi Admin, gửi email hoặc xử lý AI thật.
                </div>
                <div id="recruiter-feedback" class="alert alert-success" role="status" hidden></div>
                <div id="recruiter-error" class="alert alert-danger" role="alert" hidden></div>
                @if (session('company_notice'))
                    <div class="alert alert-success" role="status">{{ session('company_notice') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </main>
            @include('employer.component.footer')
        </div>
        @include('employer.component.confirm-modal')
        <script type="application/json" id="recruiter-seed">{!! Illuminate\Support\Js::encode($seed) !!}</script>
        <div id="recruiter-routes" hidden
            data-dashboard="{{ route('recruiter.dashboard') }}"
            data-company="{{ route('recruiter.company') }}"
            data-company-create="{{ route('recruiter.company-create') }}"
            data-job-edit="{{ route('recruiter.job-edit') }}"
            data-job-detail="{{ route('recruiter.job-detail') }}"
            data-jobs="{{ route('recruiter.jobs') }}"
            data-applications="{{ route('recruiter.applications') }}"
            data-application-detail="{{ route('recruiter.application-detail') }}">
        </div>
        <script src="{{ asset('recruiter/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('recruiter/js/dashboard.js') }}"></script>
        <script src="{{ asset('recruiter/js/recruiter-domain.js') }}"></script>
        @if ($page === 'dashboard')
            <script src="{{ asset('recruiter/js/recruiter-charts.js') }}"></script>
        @endif
        <script src="{{ asset('recruiter/js/recruiter-ai.js') }}"></script>
        <script src="{{ asset('recruiter/js/recruiter-views.js') }}"></script>
        <script src="{{ asset('recruiter/js/recruiter-categories.js') }}"></script>
        <script src="{{ asset('recruiter/js/company-profile-form.js') }}"></script>
        <script src="{{ asset('recruiter/js/recruiter.js') }}"></script>
    </body>
</html>
