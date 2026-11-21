@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @forelse ($savedJobs as $savedJob)
                <p class="small text-muted">Đã lưu: {{ $savedJob['saved_at']->format('d/m/Y H:i') }}</p>
                @if ($savedJob['card'])
                    <ul class="job-listings mb-3">
                        @include('Candidate.job-row', ['job' => $savedJob['card']])
                    </ul>
                    @if ($savedJob['card']['can_view'])
                        <a href="{{ route('jobs.show', $savedJob['job_id']) }}" class="btn btn-primary text-white mb-4">Xem và ứng tuyển</a>
                    @else
                        <button class="btn btn-light mb-4" disabled>Không nhận ứng tuyển</button>
                    @endif
                @else
                    <div class="bg-light p-4 border rounded mb-3">
                        <h2 class="h5">Công việc không khả dụng</h2>
                        <p>Tin đã được ẩn. Nội dung chi tiết không được công khai.</p>
                        <form action="{{ route('candidate.unsave', $savedJob['job_id']) }}" method="post">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-light">Bỏ lưu</button>
                        </form>
                    </div>
                @endif
            @empty
                @include('Candidate.empty-state', ['emptyTitle' => 'Chưa có việc làm đã lưu'])
            @endforelse
            {{ $savedJobs->links('pagination::bootstrap-4') }}
        </div>
    </section>
@endsection
