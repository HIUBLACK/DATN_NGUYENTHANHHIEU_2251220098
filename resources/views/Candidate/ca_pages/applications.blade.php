@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            @forelse ($demo['applications'] as $application)
                <article class="p-4 border rounded mb-4" id="application-{{ $application['id'] }}">
                    <div class="row">
                        <div class="col-md-8">
                            <h2 class="h5">{{ $application['job_title'] }}</h2>
                            <p>{{ $application['company'] }} · Lượt {{ $application['attempt'] }}/2</p>
                            <p class="small">CV: {{ $application['resume_name'] }} · {{ $application['filename'] }} · {{ $application['date'] }}</p>
                        </div>
                        <div class="col-md-4 text-md-right">
                            <span class="badge badge-light border">{{ $statusLabels[$application['status']] }}</span>
                        </div>
                    </div>
                    <details class="mb-3">
                        <summary>Xem chi tiết và lịch sử</summary>
                        <p class="mt-3">{{ $application['letter'] }}</p>
                        <ol class="candidate-timeline mt-3">
                            @foreach ($application['timeline'] as $event)
                                <li>
                                    <strong>{{ $statusLabels[$event['status']] }}</strong>
                                    <p class="small mb-0">{{ $event['date'] }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </details>
                    @if (in_array($application['status'], ['applied', 'reviewing', 'interview']))
                        <form action="{{ route('candidate.withdraw', $application['id']) }}" method="post" data-confirm="Rút hồ sơ này? Chỉ được ứng tuyển lại một lần sau lượt đầu đã rút, khi tin còn hợp lệ.">
                            @csrf
                            <button class="btn btn-outline-danger">Rút hồ sơ</button>
                        </form>
                    @endif
                    @if ($application['can_reapply'])
                        <a href="{{ route('jobs.show', $application['job_id']) }}" class="btn btn-primary text-white">Ứng tuyển lại</a>
                    @endif
                </article>
            @empty
                @include('Candidate.empty-state', ['emptyTitle' => 'Chưa có hồ sơ ứng tuyển'])
            @endforelse
            <details class="mt-4">
                <summary>Kiểm tra sáu trạng thái hồ sơ</summary>
                <form action="{{ route('demo.applications') }}" method="post" class="mt-3">
                    @csrf
                    <p>Thêm hồ sơ minh họa để xem trạng thái và timeline; không nộp hồ sơ thật.</p>
                    <button class="btn btn-light">Nạp hồ sơ mẫu</button>
                </form>
            </details>
        </div>
    </section>
@endsection
