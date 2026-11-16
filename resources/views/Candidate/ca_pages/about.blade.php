@extends('Candidate.master')

@section('content')
    <section class="section-hero overlay inner-page bg-image jobboard-hero-image" id="home-section">
        <div class="container">
            <div class="row">
                <div class="col-md-7">
                    <h1 class="text-white font-weight-bold">
                        Giới thiệu
                    </h1>
                    <div class="custom-breadcrumbs">
                        <a href="{{ route('jobs') }}">
                            Trang chủ
                        </a>
                        <span class="mx-2 slash">
                            /
                        </span>
                        <span class="text-white">
                            <strong>
                                Giới thiệu
                            </strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 jobboard-hero-image bg-image overlay-primary fixed overlay" id="next-section">
        <div class="container">
            <div class="row mb-5 justify-content-center">
                <div class="col-md-7 text-center">
                    <h2 class="section-title mb-2 text-white">
                        VIEJOB AI — Số liệu minh họa
                    </h2>
                    <p class="lead text-white">
                        Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn. Nội dung minh họa cho giao diện VIEJOB AI.
                    </p>
                </div>
            </div>
            <div class="row pb-0 block__19738 section-counter">
                <div class="col-6 col-md-6 col-lg-3 mb-5 mb-lg-0">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <strong class="number" data-number="1930">
                            0
                        </strong>
                    </div>
                    <span class="caption">
                        Ứng viên
                    </span>
                </div>
                <div class="col-6 col-md-6 col-lg-3 mb-5 mb-lg-0">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <strong class="number" data-number="54">
                            0
                        </strong>
                    </div>
                    <span class="caption">
                        Việc làm đã đăng
                    </span>
                </div>
                <div class="col-6 col-md-6 col-lg-3 mb-5 mb-lg-0">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <strong class="number" data-number="120">
                            0
                        </strong>
                    </div>
                    <span class="caption">
                        Vị trí đã tuyển
                    </span>
                </div>
                <div class="col-6 col-md-6 col-lg-3 mb-5 mb-lg-0">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <strong class="number" data-number="550">
                            0
                        </strong>
                    </div>
                    <span class="caption">
                        Công ty
                    </span>
                </div>
            </div>
        </div>
    </section>

    <section class="site-section pb-0">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <a class="block__96788" data-fancybox="" data-ratio="2" href="https://vimeo.com/317571768" rel="noopener">
                        <span class="play-icon">
                            <span class="icon-play">
                            </span>
                        </span>
                        <img alt="Hình ảnh từ template JobBoard" class="img-fluid img-shadow" src="{{ asset('jobboard/images/sq_img_6.jpg') }}"/>
                    </a>
                </div>
                <div class="col-lg-5 ml-auto">
                    <h2 class="section-title mb-3">
                        Cơ hội dành cho lập trình viên và người làm tự do
                    </h2>
                    <p class="lead">
                        Tìm kiếm công việc theo kỹ năng, địa điểm và định hướng nghề nghiệp của bạn.
                    </p>
                    <p>
                        Quản lý CV, lưu cơ hội phù hợp và theo dõi hồ sơ ứng tuyển tại một nơi. Các công ty và số liệu trên trang là nội dung minh họa.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="site-section pt-0">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0 order-md-2">
                    <a class="block__96788" data-fancybox="" data-ratio="2" href="https://vimeo.com/317571768" rel="noopener">
                        <span class="play-icon">
                            <span class="icon-play">
                            </span>
                        </span>
                        <img alt="Hình ảnh từ template JobBoard" class="img-fluid img-shadow" src="{{ asset('jobboard/images/sq_img_8.jpg') }}"/>
                    </a>
                </div>
                <div class="col-lg-5 mr-auto order-md-1 mb-5 mb-lg-0">
                    <h2 class="section-title mb-3">
                        Đồng hành cùng người tìm việc
                    </h2>
                    <p class="lead">
                        Tìm kiếm công việc theo kỹ năng, địa điểm và định hướng nghề nghiệp của bạn.
                    </p>
                    <p>
                        Quản lý CV, lưu cơ hội phù hợp và theo dõi hồ sơ ứng tuyển tại một nơi. Các công ty và số liệu trên trang là nội dung minh họa.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="site-section">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center" data-aos="fade">
                    <h2 class="section-title mb-3">
                        Đội ngũ minh họa
                    </h2>
                </div>
            </div>
            <div class="row align-items-center block__69944">
                <div class="col-md-6">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid mb-4 rounded" src="{{ asset('jobboard/images/person_6.jpg') }}"/>
                </div>
                <div class="col-md-6">
                    <h3>
                        Elisabeth Smith
                    </h3>
                    <p class="text-muted">
                        Giám đốc sáng tạo
                    </p>
                    <p>
                        Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn. Nội dung minh họa cho giao diện VIEJOB AI.
                    </p>
                    <div class="social mt-4">
                        <a href="{{ route('jobs') }}">
                            <span class="icon-facebook">
                            </span>
                        </a>
                        <a href="{{ route('jobs') }}">
                            <span class="icon-twitter">
                            </span>
                        </a>
                        <a href="{{ route('jobs') }}">
                            <span class="icon-instagram">
                            </span>
                        </a>
                        <a href="{{ route('jobs') }}">
                            <span class="icon-linkedin">
                            </span>
                        </a>
                    </div>
                </div>
                <div class="col-md-6 order-md-2 ml-md-auto">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid mb-4 rounded" src="{{ asset('jobboard/images/person_5.jpg') }}"/>
                </div>
                <div class="col-md-6">
                    <h3>
                        Chintan Patel
                    </h3>
                    <p class="text-muted">
                        Giám đốc sáng tạo
                    </p>
                    <p>
                        Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn. Nội dung minh họa cho giao diện VIEJOB AI.
                    </p>
                    <div class="social mt-4">
                        <a href="{{ route('jobs') }}">
                            <span class="icon-facebook">
                            </span>
                        </a>
                        <a href="{{ route('jobs') }}">
                            <span class="icon-twitter">
                            </span>
                        </a>
                        <a href="{{ route('jobs') }}">
                            <span class="icon-instagram">
                            </span>
                        </a>
                        <a href="{{ route('jobs') }}">
                            <span class="icon-linkedin">
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
