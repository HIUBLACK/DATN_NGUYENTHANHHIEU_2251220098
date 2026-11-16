<aside class="sidebar-wrapper" id="sidebar" aria-label="Điều hướng nhà tuyển dụng">
    <a href="{{ route('recruiter.dashboard') }}" class="sidebar-brand">
        <i class="bi bi-asterisk" aria-hidden="true"></i>
        <span>VIEJOB</span>
    </a>
    <div class="flex-grow-1 overflow-y-auto">
        <div class="sidebar-menu-section">
            <div class="sidebar-menu-title">Nhà tuyển dụng</div>
            <ul class="sidebar-menu-list">
                <li class="sidebar-menu-item">
                    <a href="{{ route('recruiter.dashboard') }}" @class(['sidebar-menu-link', 'active' => $section === 'dashboard'])>
                        <i class="bi bi-grid-fill" aria-hidden="true"></i>
                        <span>Tổng quan</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('recruiter.company') }}" @class(['sidebar-menu-link', 'active' => $section === 'company'])>
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <span>Doanh nghiệp</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('recruiter.jobs') }}" @class(['sidebar-menu-link', 'active' => $section === 'jobs'])>
                        <i class="bi bi-briefcase" aria-hidden="true"></i>
                        <span>Quản lý tin tuyển dụng</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('recruiter.applications') }}" @class(['sidebar-menu-link', 'active' => $section === 'applications'])>
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <span>Hồ sơ ứng tuyển</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('recruiter.notifications') }}" @class(['sidebar-menu-link', 'active' => $section === 'notifications'])>
                        <i class="bi bi-bell" aria-hidden="true"></i>
                        <span>Thông báo</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('recruiter.account') }}" @class(['sidebar-menu-link', 'active' => $section === 'account'])>
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <span>Tài khoản</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button type="submit" class="sidebar-menu-link recruiter-sidebar-button">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                            <span>Đăng xuất</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="sidebar-profile">
        <img src="{{ asset('recruiter/images/avatar.png') }}" alt="Ảnh đại diện minh họa" class="sidebar-profile-img">
        <div class="sidebar-profile-info">
            <div class="sidebar-profile-name">{{ auth()->user()->name }}</div>
            <div class="sidebar-profile-email">{{ auth()->user()->email }}</div>
        </div>
    </div>
</aside>
