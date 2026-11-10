<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title }} — VIEJOB Admin</title>
        <link rel="icon" href="{{ asset('admin-assets/images/favicon.ico') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/libs/bootstrap/css/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/libs/bootstrap-icons/bootstrap-icons.css') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/css/main.css') }}">
        <link rel="stylesheet" href="{{ asset('admin-assets/css/admin.css') }}">
    </head>
    <body data-admin-page="{{ $page }}">
        @include('Admin.component.sidebar')
        <div class="main-wrapper">
            @include('Admin.component.header')
            <div class="page-header">
                <div>
                    <h1 class="page-title">{{ $title }}</h1>
                    <p class="page-subtitle">Điều hành và giám sát hoạt động tuyển dụng</p>
                </div>
                <nav aria-label="Đường dẫn">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Tổng quan</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                    </ol>
                </nav>
            </div>
            <main id="admin-content">
                <div class="alert alert-light border admin-demo-notice" role="note">
                    <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
                    Bản demo giao diện. Thao tác chỉ mô phỏng trong tab trình duyệt, chưa lưu database, chưa khóa tài khoản thật hoặc gửi thông báo.
                </div>
                <div id="admin-feedback" class="alert alert-success" role="status" hidden></div>
                <div id="admin-error" class="alert alert-danger" role="alert" hidden></div>
                @yield('content')
            </main>
            @include('Admin.component.footer')
        </div>
        @include('Admin.component.confirm-modal')
        <script type="application/json" id="admin-seed">{!! Illuminate\Support\Js::encode($seed) !!}</script>
        <div id="admin-routes" hidden
            data-dashboard="{{ route('admin.dashboard') }}"
            data-users="{{ route('admin.users.index') }}"
            data-user-detail="{{ route('admin.users.show') }}"
            data-user-history="{{ route('admin.users.history') }}"
            data-companies="{{ route('admin.companies.index') }}"
            data-company-detail="{{ route('admin.companies.show') }}"
            data-jobs="{{ route('admin.jobs.index') }}"
            data-job-detail="{{ route('admin.jobs.show') }}"
            data-job-history="{{ route('admin.jobs.history') }}"
            data-queue="{{ route('admin.jobs.queue') }}"
            data-applications="{{ route('admin.applications.index') }}"
            data-application-detail="{{ route('admin.applications.show') }}"
            data-occupations="{{ route('admin.occupations.index') }}"
            data-specialties="{{ route('admin.specialties.index') }}">
        </div>
        <script src="{{ asset('admin-assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('admin-assets/js/dashboard.js') }}"></script>
        @if ($page === 'dashboard')
            <script src="{{ asset('admin-assets/libs/apexcharts/apexcharts.min.js') }}"></script>
            <script src="{{ asset('admin-assets/js/admin-dashboard.js') }}"></script>
        @endif
        <script src="{{ asset('admin-assets/js/admin-domain.js') }}"></script>
        <script src="{{ asset('admin-assets/js/admin-ui.js') }}"></script>
        <script src="{{ asset('admin-assets/js/admin-lists.js') }}"></script>
        <script src="{{ asset('admin-assets/js/admin-details.js') }}"></script>
        <script src="{{ asset('admin-assets/js/admin-categories-domain.js') }}"></script>
        <script src="{{ asset('admin-assets/js/admin-categories.js') }}"></script>
        <script src="{{ asset('admin-assets/js/admin.js') }}"></script>
    </body>
</html>
