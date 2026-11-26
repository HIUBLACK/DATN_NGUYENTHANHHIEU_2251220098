<header class="navbar-custom">
    <div class="navbar-left">
        <button class="btn-desktop-toggle d-none d-xl-flex align-items-center justify-content-center me-3" id="desktop-sidebar-toggle" aria-label="Thu gọn thanh bên">
            <i class="bi bi-chevron-bar-left" aria-hidden="true"></i>
        </button>
        <button class="sidebar-toggle-btn me-2" id="sidebar-toggle" aria-label="Mở điều hướng">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <a href="{{ route('recruiter.job-create') }}" class="btn-quick-action text-decoration-none">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>Tạo tin</span>
        </a>
    </div>
    <div class="navbar-actions">
        <a href="{{ route('recruiter.notifications') }}" class="navbar-action-btn" aria-label="Thông báo">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <span class="navbar-action-badge" title="{{ $recruiterUnreadCount }} thông báo chưa đọc" aria-label="{{ $recruiterUnreadCount }} thông báo chưa đọc" @if ($recruiterUnreadCount === 0) hidden @endif></span>
        </a>
        <a href="{{ route('recruiter.account') }}" class="navbar-profile-btn text-decoration-none">
            <img src="{{ asset('recruiter/images/avatar.png') }}" alt="Ảnh minh họa" class="navbar-profile-img">
            <span class="navbar-profile-name d-none d-md-inline">{{ auth()->user()->name }}</span>
        </a>
    </div>
</header>
