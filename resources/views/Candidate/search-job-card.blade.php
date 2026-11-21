<article class="search-job-card">
    <div class="search-job-card-top">
        @if ($job['real'] ?? false)
            @if ($job['logo_url'])
                <img src="{{ $job['logo_url'] }}" alt="Logo {{ $job['company'] }}" class="search-job-logo" loading="lazy">
            @else
                <span class="search-job-logo" aria-hidden="true">DN</span>
            @endif
        @else
            <img src="{{ asset('jobboard/images/'.$job['logo']) }}" alt="Logo minh họa {{ $job['company'] }}" class="search-job-logo" loading="lazy">
        @endif
        <div class="search-job-identity">
            <h3>
                <a href="{{ route(($job['real'] ?? false) ? 'jobs.show' : 'jobs.demo', $job['id']) }}">{{ $job['title'] }}</a>
            </h3>
            <p>{{ $job['company'] }}</p>
        </div>
    </div>
    <p class="search-job-salary">
        <span class="icon-money mr-2" aria-hidden="true"></span>
        {{ $job['salary_min'] }}–{{ $job['salary_max'] }} triệu/tháng
    </p>
    <div class="search-job-meta">
        <span>
            <span class="icon-map-marker mr-1" aria-hidden="true"></span>
            {{ $job['location'] }}
        </span>
        <span>{{ $job['level'] }}</span>
        <span>{{ $job['mode'] }}</span>
        <span>{{ $job['type'] }}</span>
    </div>
    <p class="search-job-skills">{{ implode(' · ', $job['skills']) }}</p>
    <div class="search-job-status">
        <span class="badge {{ $job['status'] === 'open' ? 'badge-primary' : 'badge-secondary' }}">{{ $statusLabels[$job['status']] }}</span>
        <span>
            <span class="icon-calendar mr-1" aria-hidden="true"></span>
            Hạn: {{ $job['deadline']->format('d/m/Y') }}
        </span>
    </div>
    <div class="search-job-actions">
        <a href="{{ route(($job['real'] ?? false) ? 'jobs.show' : 'jobs.demo', $job['id']) }}" class="btn btn-outline-primary btn-sm">Xem chi tiết</a>
        @include('Candidate.save-job-button')
    </div>
</article>
