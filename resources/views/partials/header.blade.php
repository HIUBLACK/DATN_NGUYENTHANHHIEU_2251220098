<header class="site-header"><div class="header-inner">
    <a href="{{ route('home') }}" class="brand" aria-label="VIEJOB AI - Trang chủ"><span class="brand-mark">v<span>↗</span></span>VIEJOB<span class="brand-ai">AI</span></a>
    <button type="button" class="icon-button menu-toggle" aria-controls="site-nav" aria-expanded="false" aria-label="Mở danh mục"><i class="fas fa-bars" aria-hidden="true"></i></button>
    <nav id="site-nav" class="site-nav" aria-label="Điều hướng chính">
        @foreach(['home'=>'Trang chủ','jobs'=>'Việc làm','companies'=>'Công ty','about'=>'Giới thiệu'] as $name=>$label)
            <a href="{{ route($name) }}" @class(['active'=>request()->routeIs($name) || ($name==='jobs' && request()->routeIs('jobs.show'))]) @if(request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <div class="nav-account" data-guest><a href="{{ route('login') }}">Đăng nhập</a><a class="button compact" href="{{ route('register') }}">Đăng ký</a></div>
        <div class="nav-account" data-member hidden><a class="button secondary compact" href="{{ route('candidate.ca.home') }}"><i class="far fa-user" aria-hidden="true"></i> Không gian ứng viên</a></div>
        <a class="employer-link" href="{{ route('employer') }}">Nhà tuyển dụng ↗</a>
    </nav>
</div></header>
