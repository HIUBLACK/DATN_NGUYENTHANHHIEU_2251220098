@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            @forelse ($savedJobs as $savedJob)
                @if ($savedJob)
                    <ul class="job-listings mb-3">
                        @include('Candidate.job-row', ['job' => $savedJob])
                    </ul>
                    @if ($savedJob['status'] === 'open')
                        <a href="{{ route('jobs.show', $savedJob['id']) }}" class="btn btn-primary text-white mb-4">Xem và ứng tuyển</a>
                    @else
                        <button class="btn btn-light mb-4" disabled>Không nhận ứng tuyển</button>
                    @endif
                @else
                    <div class="bg-light p-4 border rounded mb-3">
                        <h2 class="h5">Công việc không khả dụng</h2>
                        <p>Tin đã được ẩn. Nội dung chi tiết không được công khai.</p>
                        <form action="{{ route('candidate.save', 999) }}" method="post">
                            @csrf
                            <button class="btn btn-light">Bỏ lưu</button>
                        </form>
                    </div>
                @endif
            @empty
                @include('Candidate.empty-state', ['emptyTitle' => 'Chưa có việc làm đã lưu'])
            @endforelse
            <details class="mt-4">
                <summary>Kiểm tra tin không khả dụng</summary>
                <form action="{{ route('candidate.save', 999) }}" method="post" class="mt-3">
                    @csrf
                    <button class="btn btn-light">Thêm/bỏ tin ẩn minh họa</button>
                </form>
            </details>
        </div>
    </section>
@endsection
