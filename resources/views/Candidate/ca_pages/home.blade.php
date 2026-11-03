@extends('Candidate.master')

@section('content')
    <section class="home-section section-hero overlay bg-image jobboard-hero-image" id="home-section">
        <div class="container">
            <div class="row align-items-center justify-content-center">
                <div class="col-md-12">
                    <div class="mb-5 text-center">
                        <h1 class="text-white font-weight-bold">
                            Tìm công việc mơ ước của bạn
                        </h1>
                        <p>
                            Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn. Nội dung minh họa cho giao diện VIEJOB AI.
                        </p>
                    </div>
                    <form action="{{ route('jobs') }}" class="search-jobs-form" method="get">
                        <div class="row mb-5">
                            <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4 mb-lg-0">
                                <input aria-label="Từ khóa tìm việc" class="form-control form-control-lg" name="q" placeholder="Vị trí, kỹ năng hoặc công ty..." type="text"/>
                            </div>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4 mb-lg-0">
                                <select aria-label="Địa điểm" class="selectpicker" data-live-search="true" data-style="btn-white btn-lg" data-width="100%" name="location" title="Địa điểm">
                                    <option value="">
                                        Tất cả
                                    </option>
                                    <option value="Hà Nội">
                                        Hà Nội
                                    </option>
                                    <option value="TP. Hồ Chí Minh">
                                        TP. Hồ Chí Minh
                                    </option>
                                    <option value="Đà Nẵng">
                                        Đà Nẵng
                                    </option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4 mb-lg-0">
                                <select aria-label="Loại công việc" class="selectpicker" data-live-search="true" data-style="btn-white btn-lg" data-width="100%" name="type" title="Loại công việc">
                                    <option value="">
                                        Tất cả
                                    </option>
                                    <option value="Toàn thời gian">
                                        Toàn thời gian
                                    </option>
                                    <option value="Bán thời gian">
                                        Bán thời gian
                                    </option>
                                    <option value="Thực tập">
                                        Thực tập
                                    </option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4 mb-lg-0">
                                <button class="btn btn-primary btn-lg btn-block text-white btn-search" type="submit">
                                    <span class="icon-search icon mr-2">
                                    </span>
                                    Tìm việc làm
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 popular-keywords">
                                <h3>
                                    Từ khóa nổi bật:
                                </h3>
                                <ul class="keywords list-unstyled m-0 p-0">
                                    <li>
                                        <a class="" href="{{ route('jobs', ['q' => 'UI']) }}">
                                            Thiết kế UI/UX
                                        </a>
                                    </li>
                                    <li>
                                        <a class="" href="{{ route('jobs', ['q' => 'Python']) }}">
                                            Python
                                        </a>
                                    </li>
                                    <li>
                                        <a class="" href="{{ route('jobs', ['q' => 'Lập trình']) }}">
                                            Lập trình viên
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <a class="scroll-button smoothscroll" href="#next">
            <span class="icon-keyboard_arrow_down">
            </span>
        </a>
    </section>

    <section class="py-5 jobboard-hero-image bg-image overlay-primary fixed overlay" id="next">
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

    <section class="site-section">
        <div class="container">
            <div class="row mb-5 justify-content-center">
                <div class="col-md-7 text-center">
                    <h2 class="section-title mb-2">
                        Việc làm công nghệ nổi bật
                    </h2>
                </div>
            </div>
            <ul class="job-listings mb-5">
                @foreach ($catalogJobs->take(7) as $job)
                    @include('Candidate.job-row', ['compact' => true])
                @endforeach
            </ul>
            <div class="row pagination-wrap">
                <div class="col-md-6 text-center text-md-left mb-4 mb-md-0">
                    <span>
                        Các công việc hiển thị là dữ liệu mẫu
                    </span>
                </div>
                <div class="col-md-6 text-center text-md-right">
                    <div class="custom-pagination ml-auto">
                        <a class="prev" href="{{ route('jobs') }}">
                            Trước
                        </a>
                        <div class="d-inline-block">
                            <a class="active" href="{{ route('jobs') }}">
                                1
                            </a>
                            <a href="{{ route('jobs', ['page' => 2]) }}">
                                2
                            </a>
                        </div>
                        <a class="next" href="{{ route('jobs', ['page' => 2]) }}">
                            Tiếp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 jobboard-hero-image bg-image overlay-primary fixed overlay">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="text-white">
                        Bạn đang tìm việc làm?
                    </h2>
                    <p class="mb-0 text-white lead">
                        Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn. Nội dung minh họa cho giao diện VIEJOB AI.
                    </p>
                </div>
                <div class="col-md-3 ml-auto">
                    <a class="btn btn-warning btn-block btn-lg" href="{{ route('register') }}">
                        Đăng ký
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="site-section py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 text-center mt-4 mb-5">
                    <div class="row justify-content-center">
                        <div class="col-md-7">
                            <h2 class="section-title mb-2">
                                Các công ty trong giao diện mẫu
                            </h2>
                            <p class="lead">
                                Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn. Nội dung minh họa cho giao diện VIEJOB AI.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6 text-center">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid logo-1" src="{{ asset('jobboard/images/logo_mailchimp.svg') }}"/>
                </div>
                <div class="col-6 col-lg-3 col-md-6 text-center">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid logo-2" src="{{ asset('jobboard/images/logo_paypal.svg') }}"/>
                </div>
                <div class="col-6 col-lg-3 col-md-6 text-center">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid logo-3" src="{{ asset('jobboard/images/logo_stripe.svg') }}"/>
                </div>
                <div class="col-6 col-lg-3 col-md-6 text-center">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid logo-4" src="{{ asset('jobboard/images/logo_visa.svg') }}"/>
                </div>
                <div class="col-6 col-lg-3 col-md-6 text-center">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid logo-5" src="{{ asset('jobboard/images/logo_apple.svg') }}"/>
                </div>
                <div class="col-6 col-lg-3 col-md-6 text-center">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid logo-6" src="{{ asset('jobboard/images/logo_tinder.svg') }}"/>
                </div>
                <div class="col-6 col-lg-3 col-md-6 text-center">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid logo-7" src="{{ asset('jobboard/images/logo_sony.svg') }}"/>
                </div>
                <div class="col-6 col-lg-3 col-md-6 text-center">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid logo-8" src="{{ asset('jobboard/images/logo_airbnb.svg') }}"/>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-light pt-5 testimony-full">
        <div class="owl-carousel single-carousel">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6 align-self-center text-center text-lg-left">
                        <blockquote>
                            <p>
                                Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn. Nội dung minh họa cho giao diện VIEJOB AI.
                            </p>
                            <p>
                                <cite>
                                    — Corey Woods, @Dribbble
                                </cite>
                            </p>
                        </blockquote>
                    </div>
                    <div class="col-lg-6 align-self-end text-center text-lg-right">
                        <img alt="Hình ảnh từ template JobBoard" class="img-fluid mb-0" src="{{ asset('jobboard/images/person_transparent_2.png') }}"/>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="row">
                    <div class="col-lg-6 align-self-center text-center text-lg-left">
                        <blockquote>
                            <p>
                                Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn. Nội dung minh họa cho giao diện VIEJOB AI.
                            </p>
                            <p>
                                <cite>
                                    — Chris Peters, @Google
                                </cite>
                            </p>
                        </blockquote>
                    </div>
                    <div class="col-lg-6 align-self-end text-center text-lg-right">
                        <img alt="Hình ảnh từ template JobBoard" class="img-fluid mb-0" src="{{ asset('jobboard/images/person_transparent.png') }}"/>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pt-5 jobboard-hero-image bg-image overlay-primary fixed overlay">
        <div class="container">
            <div class="row">
                <div class="col-md-6 align-self-center text-center text-md-left mb-5 mb-md-0">
                    <h2 class="text-white">
                        Khám phá cơ hội mọi lúc
                    </h2>
                    <p class="mb-5 lead text-white">
                        Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn. Nội dung minh họa cho giao diện VIEJOB AI.
                    </p>
                    <p class="mb-0">
                        <a class="btn btn-dark btn-md px-4 border-width-2" href="{{ route('jobs') }}">
                            <span class="icon-apple mr-3">
                            </span>
                            Tìm việc làm
                        </a>
                        <a class="btn btn-dark btn-md px-4 border-width-2" href="{{ route('candidate.resumes') }}">
                            <span class="icon-android mr-3">
                            </span>
                            Quản lý CV
                        </a>
                    </p>
                </div>
                <div class="col-md-6 ml-auto align-self-end">
                    <img alt="Hình ảnh từ template JobBoard" class="img-fluid" src="{{ asset('jobboard/images/apps.png') }}"/>
                </div>
            </div>
        </div>
    </section>
@endsection
