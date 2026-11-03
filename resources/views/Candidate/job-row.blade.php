<li class="job-listing d-block d-sm-flex pb-3 pb-sm-0 align-items-center">
    <a href="{{ route('jobs.show', $job['id']) }}" aria-label="Chi tiết {{ $job['title'] }}"></a>
    <div class="job-listing-logo">
        <img src="{{ asset('jobboard/images/'.$job['logo']) }}" alt="Logo minh họa {{ $job['company'] }}" class="img-fluid">
    </div>
    <div class="job-listing-about d-sm-flex custom-width w-100 justify-content-between mx-4">
        <div class="job-listing-position custom-width w-50 mb-3 mb-sm-0">
            <h2>{{ $job['title'] }}</h2>
            <strong>{{ $job['company'] }}</strong>
            @unless ($compact ?? false)
                <p class="small mb-1">
                    {{ $job['salary_min'] }}–{{ $job['salary_max'] }} triệu/tháng · {{ $job['level'] }}
                </p>
                <p class="small mb-1">{{ implode(' · ', $job['skills']) }}</p>
                <p class="small mb-1">Hạn: {{ $job['deadline']->format('d/m/Y') }}</p>
            @endunless
        </div>
        <div class="job-listing-location mb-3 mb-sm-0 custom-width w-25">
            <span class="icon-room" aria-hidden="true"></span>
            {{ $job['location'] }}
            @unless ($compact ?? false)
                <p class="small">{{ $job['mode'] }}</p>
            @endunless
        </div>
        <div class="job-listing-meta">
            <span class="badge badge-success">{{ $statusLabels[$job['status']] }}</span>
            @unless ($compact ?? false)
                <a class="btn btn-outline-primary btn-sm candidate-detail-link mt-2" href="{{ route('jobs.show', $job['id']) }}">Xem chi tiết</a>
                <form action="{{ route('candidate.save', $job['id']) }}" method="post" class="candidate-row-action mt-2">
                    @csrf
                    <button type="submit" class="btn btn-light btn-sm" aria-label="Lưu hoặc bỏ lưu {{ $job['title'] }}">
                        @if (in_array($job['id'], $demo['saved']))
                            Bỏ lưu
                        @else
                            Lưu việc
                        @endif
                    </button>
                </form>
            @endunless
        </div>
    </div>
</li>
