<div class="dropdown notification-dropdown mr-3">
    <button type="button" class="notification-toggle" data-toggle="dropdown" aria-expanded="false" aria-controls="notification-panel" aria-label="Thông báo, {{ $unreadNotificationCount }} chưa đọc">
        <span class="icon-bell" aria-hidden="true"></span>
        @if ($unreadNotificationCount > 0)
            <span class="notification-count" aria-hidden="true">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-right notification-panel" id="notification-panel" data-notification-panel>
        <div class="notification-panel-heading">
            <h2>Thông báo</h2>
            <form action="{{ route('candidate.read', 'all') }}" method="post">
                @csrf
                <button type="submit" class="notification-read-all" title="Đánh dấu tất cả đã đọc" aria-label="Đánh dấu tất cả đã đọc" @disabled($unreadNotificationCount === 0)>
                    <span class="icon-check-circle" aria-hidden="true"></span>
                </button>
            </form>
        </div>
        <div class="notification-filters" role="group" aria-label="Lọc thông báo">
            <button type="button" data-notification-filter="all" aria-pressed="true">Tất cả</button>
            <button type="button" data-notification-filter="unread" aria-pressed="false">Chưa đọc ({{ $unreadNotificationCount }})</button>
            <button type="button" data-notification-filter="read" aria-pressed="false">Đã đọc</button>
        </div>
        <div class="notification-panel-list">
            @foreach ($demo['notifications'] as $notification)
                <article class="notification-panel-item" data-notification-state="{{ $notification['read'] ? 'read' : 'unread' }}">
                    <span class="notification-item-icon icon-briefcase" aria-hidden="true"></span>
                    <div class="notification-item-content">
                        <a href="{{ route('candidate.applications') }}#application-{{ $notification['application_id'] }}" class="notification-item-title">
                            {{ $statusLabels[$notification['status']] }} — {{ $notification['title'] }}
                        </a>
                        @if ($notification['status'] === 'reviewing')
                            <p>Thông báo nội bộ, không gửi email.</p>
                        @else
                            <p>Cập nhật hồ sơ demo, chưa gửi email thật.</p>
                        @endif
                        <p class="notification-item-date">{{ $notification['date'] }}</p>
                        @unless ($notification['read'])
                            <form action="{{ route('candidate.read', $notification['id']) }}" method="post">
                                @csrf
                                <button type="submit" class="notification-read-one">Đánh dấu đã đọc</button>
                            </form>
                        @endunless
                    </div>
                    @unless ($notification['read'])
                        <span class="notification-unread-dot" role="img" aria-label="Chưa đọc"></span>
                    @endunless
                </article>
            @endforeach
            <p class="notification-panel-empty" data-notification-empty @if (count($demo['notifications']) > 0) hidden @endif>Không có thông báo trong mục này.</p>
        </div>
        <a href="{{ route('candidate.notifications') }}" class="notification-view-all">Xem tất cả thông báo</a>
    </div>
</div>
