<li class="job-listing d-block d-sm-flex pb-3 pb-sm-0 align-items-center">
    @if ($job['can_view'] ?? true)
        <a href="{{ route(($job['real'] ?? false) ? 'jobs.show' : 'jobs.demo', $job['id']) }}" aria-label="Chi tiết {{ $job['title'] }}"></a>
    @endif
    <div class="job-listing-logo">
        @if ($job['real'] ?? false)
            @if ($job['logo_url'])
                <img src="{{ $job['logo_url'] }}" alt="Logo {{ $job['company'] }}" class="img-fluid">
            @else
                <span aria-hidden="true">DN</span>
            @endif
        @else
            <img src="{{ asset('jobboard/images/'.$job['logo']) }}" alt="Logo minh họa {{ $job['company'] }}" class="img-fluid">
        @endif
    </div>
    <div class="job-listing-about d-sm-flex custom-width w-100 justify-content-between mx-4">
        <div class="job-listing-position custom-width w-50 mb-3 mb-sm-0">
            <h2>{{ $job['title'] }}</h2>
            <strong>{{ $job['company'] }}</strong>
            @unless ($compact ?? false)
                <p class="small mb-1">
                    {{ ($job['salary_negotiable'] ?? false) ? 'Lương thỏa thuận' : $job['salary_min'].'–'.$job['salary_max'].' triệu/tháng' }} · {{ $job['level'] }}
                </p>
                <p class="small mb-1">{{ implode(' · ', $job['skills']) }}</p>
                @if (isset($job['can_view']))
                    <p class="small mb-1">{{ $job['position'] }}</p>
                @endif
                <p class="small mb-1">Hạn: {{ $job['deadline']->format('d/m/Y') }}</p>
            @endunless
        </div>
        <div class="job-listing-location mb-3 mb-sm-0 custom-width w-25">
            <span class="icon-room" aria-hidden="true"></span>
            {{ $job['location'] }}
            @unless ($compact ?? false)
                <p class="small">{{ $job['mode'] }}</p>
                @if (isset($job['can_view']))
                    <p class="small">{{ $job['location_detail'] }}</p>
                @endif
            @endunless
        </div>
        <div class="job-listing-meta">
            <span class="badge {{ $job['status'] === 'open' ? 'badge-success' : 'badge-secondary' }}">{{ $statusLabels[$job['status']] }}</span>
            @unless ($compact ?? false)
                @if ($job['can_view'] ?? true)
                    <a class="btn btn-outline-primary btn-sm candidate-detail-link mt-2" href="{{ route(($job['real'] ?? false) ? 'jobs.show' : 'jobs.demo', $job['id']) }}">Xem chi tiết</a>
                @endif
                @include('Candidate.save-job-button', ['saveButtonClass' => 'btn btn-light btn-sm mt-2', 'saveButtonLabel' => true])
            @endunless
        </div>
    </div>
</li>
