<div class="site-mobile-menu site-navbar-target">
    <div class="site-mobile-menu-header">
        <div class="site-mobile-menu-close mt-3">
            <button type="button" class="btn btn-light js-menu-toggle" aria-label="Đóng danh mục">
                <span class="icon-close2" aria-hidden="true"></span>
            </button>
        </div>
    </div>
    <div class="site-mobile-menu-body"></div>
</div>

<header class="site-navbar mt-3">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="site-logo col-6">
                <a href="{{ route('home') }}">VIEJOB AI</a>
            </div>

            <nav class="mx-auto site-navigation" aria-label="Điều hướng chính">
                <ul class="site-menu js-clone-nav d-none d-xl-block ml-0 pl-0">
                    <li>
                        <a href="{{ route('home') }}">Trang chủ</a>
                    </li>
                    <li>
                        <a href="{{ route('jobs') }}">Việc làm</a>
                    </li>
                    <li>
                        <a href="{{ route('companies') }}">Công ty</a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}">Giới thiệu</a>
                    </li>
                    @if ($demo['active'])
                        <li class="d-xl-none">
                            <a href="{{ route('candidate.notifications') }}">Thông báo</a>
                        </li>
                        <li class="d-xl-none">
                            <a href="{{ route('candidate.messages') }}">Tin nhắn</a>
                        </li>
                        <li class="has-children d-xl-none">
                            <a href="{{ route('candidate.profile') }}">Hồ sơ ứng viên</a>
                            <ul class="dropdown">
                                @include('Candidate.account-links')
                            </ul>
                        </li>
                    @else
                        <li class="d-xl-none">
                            <a href="{{ route('login') }}">Đăng nhập</a>
                        </li>
                        <li class="d-xl-none">
                            <a href="{{ route('register') }}">Đăng ký</a>
                        </li>
                    @endif
                    <li class="d-xl-none">
                        <a href="{{ route('employer') }}">Nhà tuyển dụng</a>
                    </li>
                </ul>
            </nav>

            <div class="right-cta-menu text-right d-flex align-items-center col-6">
                <div class="ml-auto d-none d-xl-flex align-items-center">
                    @if ($demo['active'])
                        <a class="text-white mr-3" href="{{ route('candidate.notifications') }}" aria-label="Thông báo">
                            <span class="icon-bell" aria-hidden="true"></span>
                        </a>
                        <a class="text-white mr-3" href="{{ route('candidate.messages') }}" aria-label="Tin nhắn">
                            <span class="icon-envelope" aria-hidden="true"></span>
                        </a>
                        <div class="dropdown candidate-account mr-3">
                            <button class="btn btn-light rounded-circle p-0 candidate-avatar" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Tài khoản ứng viên">
                                <img src="{{ route('candidate.avatar') }}" alt="Ảnh đại diện">
                            </button>
                            <ul class="dropdown-menu dropdown-menu-right list-unstyled">
                                @include('Candidate.account-links')
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="text-white mr-3">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="btn btn-primary border-width-2 mr-2">Đăng ký</a>
                    @endif
                    <a href="{{ route('employer') }}" class="btn btn-outline-white border-width-2">Nhà tuyển dụng</a>
                </div>
                <a href="#" class="site-menu-toggle js-menu-toggle d-inline-block d-xl-none mt-lg-2 ml-auto" aria-label="Mở danh mục">
                    <span class="icon-menu h3 m-0 p-0 mt-2" aria-hidden="true"></span>
                </a>
            </div>
        </div>
    </div>
</header>
