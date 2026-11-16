<header class="navbar-custom">
    <div class="navbar-left">
        <button class="btn-desktop-toggle d-none d-xl-flex align-items-center justify-content-center me-3" id="desktop-sidebar-toggle" aria-label="Thu gọn thanh bên">
            <i class="bi bi-chevron-bar-left" aria-hidden="true"></i>
        </button>
        <button class="sidebar-toggle-btn me-2" aria-label="Mở điều hướng">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <a href="{{ route('admin.jobs.queue') }}" class="btn-quick-action text-decoration-none">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            <span>Chờ duyệt</span>
        </a>
    </div>
    <a href="{{ route('admin.account') }}" class="navbar-profile-btn text-decoration-none">
        <i class="bi bi-person-badge fs-4" aria-hidden="true"></i>
        <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
    </a>
</header>
