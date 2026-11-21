@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <article class="p-4 border rounded">
                <h2 class="h4">{{ $application->jobVersion->title }}</h2>
                <p>{{ $application->job->company->name }} · Phiên bản tin V{{ $application->jobVersion->version_number }} · Lượt {{ $application->attempt_no }}/2</p>
                <p>{{ $statusLabels[$application->status->value] }} · Nộp ngày {{ $application->applied_at->format('d/m/Y H:i') }}</p>
                <p>CV đã nộp: {{ $application->resume_snapshot_filename }} · Phiên bản CV {{ $application->resume_revision }}</p>
                <a href="{{ route('candidate.snapshot', $application->public_id) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Xem CV đã nộp</a>
                <a href="{{ route('candidate.snapshot', [$application->public_id, 'download' => 1]) }}" class="btn btn-outline-primary">Tải CV đã nộp</a>
                @if ($application->cover_letter)
                    <h3 class="h5 mt-4">Thư giới thiệu</h3>
                    <p class="company-profile-text">{{ $application->cover_letter }}</p>
                @endif
                <h3 class="h5 mt-4">Lịch sử trạng thái</h3>
                <ol class="candidate-timeline">
                    @foreach ($application->statusHistories->sortBy('id') as $event)
                        <li>{{ $statusLabels[$event->new_status->value] }} — {{ $event->created_at->format('d/m/Y H:i') }}</li>
                    @endforeach
                </ol>
                @can('withdraw', $application)
                    <form method="post" action="{{ route('candidate.withdraw', $application->public_id) }}" data-confirm="Xác nhận rút hồ sơ? Bạn chỉ được ứng tuyển lại một lần sau khi rút lượt đầu.">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Rút hồ sơ</button>
                    </form>
                @endcan
                @if ($application->status->value === 'withdrawn' && $application->attempt_no === 1)
                    <p class="mt-3">Bạn có thể ứng tuyển lại một lần nếu tin còn nhận hồ sơ.</p>
                    <a href="{{ route('jobs.show', $application->job_id) }}" class="btn btn-outline-primary">Kiểm tra tin và ứng tuyển lại</a>
                @endif
                <a href="{{ route('candidate.applications') }}" class="btn btn-light mt-3">Quay lại danh sách</a>
            </article>
        </div>
    </section>
@endsection
