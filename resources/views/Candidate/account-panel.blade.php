<div class="dropdown-menu dropdown-menu-right account-panel" data-account-panel>
    <div class="account-panel-profile">
        <img src="{{ route('candidate.avatar') }}" alt="Ảnh đại diện" class="account-panel-avatar">
        <div class="account-panel-identity">
            <a href="{{ route('candidate.profile') }}" class="account-panel-name">{{ $demo['profile']['name'] }}</a>
            <p class="account-panel-status">Tài khoản xem thử</p>
            <p class="account-panel-email" title="{{ $demo['profile']['email'] }}">{{ $demo['profile']['email'] }}</p>
        </div>
    </div>
    <div class="account-panel-groups">
        <details class="account-panel-group" open>
            <summary>
                <span class="icon-briefcase account-panel-icon" aria-hidden="true"></span>
                <span>Quản lý tìm việc</span>
                <span class="icon-chevron-down account-panel-chevron" aria-hidden="true"></span>
            </summary>
            <div class="account-panel-links">
                <a href="{{ route('candidate.saved') }}">Việc làm đã lưu</a>
                <a href="{{ route('candidate.applications') }}">Việc làm đã ứng tuyển</a>
                <a href="{{ route('candidate.recommendations') }}">Việc làm gợi ý</a>
            </div>
        </details>
        <details class="account-panel-group">
            <summary>
                <span class="icon-file-text account-panel-icon" aria-hidden="true"></span>
                <span>Quản lý CV PDF</span>
                <span class="icon-chevron-down account-panel-chevron" aria-hidden="true"></span>
            </summary>
            <div class="account-panel-links">
                <a href="{{ route('candidate.resumes') }}">CV của tôi</a>
            </div>
        </details>
        <a href="{{ route('candidate.notifications') }}" class="account-panel-direct">
            <span class="icon-bell account-panel-icon" aria-hidden="true"></span>
            <span>Thông báo</span>
        </a>
        <details class="account-panel-group">
            <summary>
                <span class="icon-shield account-panel-icon" aria-hidden="true"></span>
                <span>Cá nhân &amp; bảo mật</span>
                <span class="icon-chevron-down account-panel-chevron" aria-hidden="true"></span>
            </summary>
            <div class="account-panel-links">
                <a href="{{ route('candidate.profile') }}">Hồ sơ cá nhân</a>
                <a href="{{ route('candidate.settings') }}">Cài đặt tài khoản</a>
            </div>
        </details>
    </div>
    <form action="{{ route('demo.leave') }}" method="post" class="account-panel-logout">
        @csrf
        <button type="submit">
            <span class="icon-sign-out mr-2" aria-hidden="true"></span>
            Đăng xuất demo
        </button>
    </form>
</div>
