<aside class="sidebar-wrapper" id="sidebar" aria-label="Điều hướng quản trị">
    <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
        <i class="bi bi-asterisk" aria-hidden="true"></i>
        <span>VIEJOB</span>
    </a>
    <div class="flex-grow-1 overflow-y-auto">
        <div class="sidebar-menu-section">
            <div class="sidebar-menu-title">Quản trị hệ thống</div>
            <ul class="sidebar-menu-list">
                <li class="sidebar-menu-item">
                    <a href="{{ route('admin.dashboard') }}" @class(['sidebar-menu-link', 'active' => $section === 'dashboard'])>
                        <i class="bi bi-grid-fill" aria-hidden="true"></i>
                        <span>Tổng quan</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('admin.users.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'users'])>
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <span>Người dùng</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('admin.companies.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'companies'])>
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <span>Doanh nghiệp</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('admin.jobs.queue') }}" @class(['sidebar-menu-link', 'active' => $section === 'queue'])>
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span>Kiểm duyệt tin</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('admin.jobs.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'jobs'])>
                        <i class="bi bi-briefcase" aria-hidden="true"></i>
                        <span>Quản lý tin tuyển dụng</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('admin.applications.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'applications'])>
                        <i class="bi bi-clipboard-data" aria-hidden="true"></i>
                        <span>Giám sát ứng tuyển</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <button type="button" class="sidebar-menu-link admin-sidebar-button" data-bs-toggle="collapse"
                        data-bs-target="#admin-category-menu" aria-controls="admin-category-menu"
                        aria-expanded="{{ in_array($section, ['occupations', 'specialties', 'industries', 'skills', 'levels', 'positions']) ? 'true' : 'false' }}">
                        <i class="bi bi-diagram-3" aria-hidden="true"></i>
                        <span>Quản lý danh mục</span>
                        <i class="bi bi-chevron-down ms-auto" aria-hidden="true"></i>
                    </button>
                    <ul id="admin-category-menu" @class(['sidebar-menu-list', 'collapse', 'ms-3', 'show' => in_array($section, ['occupations', 'specialties', 'industries', 'skills', 'levels', 'positions'])])>
                        <li class="sidebar-menu-item">
                            <a href="{{ route('admin.positions.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'positions'])>
                                <i class="bi bi-person-badge" aria-hidden="true"></i>
                                <span>Vị trí công việc</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="{{ route('admin.occupations.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'occupations'])>
                                <i class="bi bi-collection" aria-hidden="true"></i>
                                <span>Nhóm nghề CNTT</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="{{ route('admin.specialties.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'specialties'])>
                                <i class="bi bi-person-workspace" aria-hidden="true"></i>
                                <span>Chuyên môn CNTT</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="{{ route('admin.industries.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'industries'])>
                                <i class="bi bi-buildings" aria-hidden="true"></i>
                                <span>Lĩnh vực</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="{{ route('admin.skills.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'skills'])>
                                <i class="bi bi-code-slash" aria-hidden="true"></i>
                                <span>Kỹ năng</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="{{ route('admin.levels.index') }}" @class(['sidebar-menu-link', 'active' => $section === 'levels'])>
                                <i class="bi bi-bar-chart-steps" aria-hidden="true"></i>
                                <span>Cấp bậc</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('admin.account') }}" @class(['sidebar-menu-link', 'active' => $section === 'account'])>
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <span>Tài khoản</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button type="submit" class="sidebar-menu-link admin-sidebar-button">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                            <span>Đăng xuất</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="sidebar-profile">
        <i class="bi bi-person-badge fs-3 text-white" aria-hidden="true"></i>
        <div class="sidebar-profile-info">
            <div class="sidebar-profile-name">{{ auth()->user()->name }}</div>
            <div class="sidebar-profile-email">{{ auth()->user()->email }}</div>
        </div>
    </div>
</aside>
