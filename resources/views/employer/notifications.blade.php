@extends('employer.layout')

@section('content')
    <p>{{ $unreadCount }} thông báo chưa đọc</p>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <label for="notification-filter" class="visually-hidden">Lọc thông báo</label>
        <form method="get" action="{{ route('recruiter.notifications') }}" class="d-flex gap-2">
            <select id="notification-filter" name="filter" class="form-select recruiter-filter-select">
                @foreach (['all' => 'Tất cả thông báo', 'unread' => 'Chưa đọc', 'applied' => 'Hồ sơ mới', 'withdrawn' => 'Đã rút đơn'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('filter', 'all') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-light">Lọc</button>
        </form>
        <form method="post" action="{{ route('recruiter.notification-read', 'all') }}">
            @csrf
            <button type="submit" id="read-all-notifications" class="btn-dark-custom">Đánh dấu tất cả đã đọc</button>
        </form>
    </div>
    <div class="card" id="recruiter-notifications">
        @forelse ($notifications as $notification)
            <article class="recruiter-notification {{ $notification->read_at ? '' : 'is-unread' }}">
                <div>
                    <a href="{{ route('recruiter.application-detail', ['id' => $notification->data['application_id']]) }}">
                        {{ $notification->data['candidate_name'] }} {{ $notification->data['status'] === 'withdrawn' ? 'đã rút đơn' : 'đã ứng tuyển' }} — {{ $notification->data['job_title'] }} · Lượt {{ $notification->data['attempt'] }}
                    </a>
                    <p class="small mb-0">{{ \App\Support\DisplayDate::format($notification->created_at) }} · {{ $notification->read_at ? 'Đã đọc' : 'Chưa đọc' }}</p>
                </div>
                @unless ($notification->read_at)
                    <form method="post" action="{{ route('recruiter.notification-read', $notification->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-light btn-sm">Đánh dấu đã đọc</button>
                    </form>
                @endunless
            </article>
        @empty
            <p class="text-muted-green p-4">Không có thông báo trong mục này.</p>
        @endforelse
    </div>
    <div class="mt-4">
        {{ $notifications->links('pagination::bootstrap-5') }}
    </div>
@endsection
