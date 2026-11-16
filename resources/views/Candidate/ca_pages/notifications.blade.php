@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            <form action="{{ route('candidate.read', 'all') }}" method="post" class="mb-4">
                @csrf
                <button class="btn btn-light">Đánh dấu tất cả đã đọc</button>
            </form>
            @forelse ($demo['notifications'] as $notification)
                <article @class(['bg-light rounded p-4 mb-3', 'candidate-notification-unread' => ! $notification['read']])>
                    <h2 class="h5">{{ $statusLabels[$notification['status']] }} — {{ $notification['title'] }}</h2>
                    <p class="small">{{ $notification['date'] }} · {{ $notification['read'] ? 'Đã đọc' : 'Chưa đọc' }}</p>
                    @if ($notification['status'] === 'reviewing')
                        <p class="small">Thông báo nội bộ, không gửi email.</p>
                    @else
                        <p class="small">Sự kiện email minh họa, chưa gửi email thật.</p>
                    @endif
                    <a href="{{ route('candidate.applications') }}#application-{{ $notification['application_id'] }}">Xem hồ sơ ứng tuyển</a>
                    @unless ($notification['read'])
                        <form action="{{ route('candidate.read', $notification['id']) }}" method="post" class="mt-3">
                            @csrf
                            <button class="btn btn-light btn-sm">Đánh dấu đã đọc</button>
                        </form>
                    @endunless
                </article>
            @empty
                @include('Candidate.empty-state', ['emptyTitle' => 'Chưa có thông báo'])
            @endforelse
        </div>
    </section>
@endsection
