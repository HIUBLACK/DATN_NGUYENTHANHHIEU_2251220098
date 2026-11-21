@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <p>{{ $applications->total() }} lượt ứng tuyển</p>
            @forelse ($applications as $application)
                <article class="p-4 border rounded mb-4" id="application-{{ $application->public_id }}">
                    <h2 class="h5">{{ $application->jobVersion->title }}</h2>
                    <p>{{ $application->job->company->name }} · Lượt {{ $application->attempt_no }}/2</p>
                    <p>{{ \App\Support\DisplayDate::format($application->applied_at) }} · {{ $statusLabels[$application->status->value] }}</p>
                    <a href="{{ route('candidate.application-detail', $application->public_id) }}" class="btn btn-outline-primary">Xem hồ sơ đã nộp</a>
                </article>
            @empty
                @include('Candidate.empty-state', ['emptyTitle' => 'Chưa có hồ sơ ứng tuyển'])
            @endforelse
            {{ $applications->links() }}
        </div>
    </section>
@endsection
