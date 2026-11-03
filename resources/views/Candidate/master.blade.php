<!doctype html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>{{ $title ?? 'Việc làm dành cho bạn' }} — VIEJOB AI</title>
        <link rel="stylesheet" href="{{ asset('jobboard/css/custom-bs.css') }}">
        <link rel="stylesheet" href="{{ asset('jobboard/css/jquery.fancybox.min.css') }}">
        <link rel="stylesheet" href="{{ asset('jobboard/css/bootstrap-select.min.css') }}">
        <link rel="stylesheet" href="{{ asset('jobboard/fonts/icomoon/style.css') }}">
        <link rel="stylesheet" href="{{ asset('jobboard/fonts/line-icons/style.css') }}">
        <link rel="stylesheet" href="{{ asset('jobboard/css/owl.carousel.min.css') }}">
        <link rel="stylesheet" href="{{ asset('jobboard/css/animate.min.css') }}">
        <link rel="stylesheet" href="{{ asset('jobboard/css/style.css') }}">
        <link rel="stylesheet" href="{{ asset('jobboard/css/candidate.css') }}">
        <link rel="stylesheet" href="{{ asset('jobboard/css/theme.css') }}">
        @if (($page ?? '') === 'jobs')
            <link rel="stylesheet" href="{{ asset('jobboard/css/jobs.css') }}">
        @endif
    </head>
    <body id="top" class="{{ ($page ?? '') === 'job' ? 'job-detail-layout' : (($page ?? '') === 'jobs' ? 'jobs-list-layout' : '') }}">
        <a class="sr-only sr-only-focusable" href="#main-content">Chuyển đến nội dung</a>
        <div id="overlayer"></div>
        <div class="loader">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Đang tải...</span>
            </div>
        </div>

        <div class="site-wrap">
            @include('Candidate.header')

            @if (($page ?? '') === 'job')
                <div class="job-header-backdrop" aria-hidden="true"></div>
            @endif

            <main id="main-content">
                @isset($title)
                    @if (! in_array($page ?? '', ['about', 'job', 'jobs']))
                        @include('Candidate.page-hero')
                    @endif
                @endisset

                @if (session('notice') || $errors->any())
                    <div class="container pt-4">
                        @if (session('notice'))
                            <div class="alert alert-success" role="status">
                                {{ session('notice') }}
                            </div>
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
                    </div>
                @endif

                @yield('content')
            </main>

            @include('Candidate.footer')
            @include('Candidate.confirm-modal')
        </div>

        <script src="{{ asset('jobboard/js/jquery.min.js') }}"></script>
        <script src="{{ asset('jobboard/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('jobboard/js/isotope.pkgd.min.js') }}"></script>
        <script src="{{ asset('jobboard/js/stickyfill.min.js') }}"></script>
        <script src="{{ asset('jobboard/js/jquery.fancybox.min.js') }}"></script>
        <script src="{{ asset('jobboard/js/jquery.easing.1.3.js') }}"></script>
        <script src="{{ asset('jobboard/js/jquery.waypoints.min.js') }}"></script>
        <script src="{{ asset('jobboard/js/jquery.animateNumber.min.js') }}"></script>
        <script src="{{ asset('jobboard/js/owl.carousel.min.js') }}"></script>
        <script src="{{ asset('jobboard/js/bootstrap-select.min.js') }}"></script>
        <script src="{{ asset('jobboard/js/navigation-bridge.js') }}"></script>
        <script src="{{ asset('jobboard/js/custom.js') }}"></script>
        <script src="{{ asset('jobboard/js/candidate.js') }}"></script>
    </body>
</html>
