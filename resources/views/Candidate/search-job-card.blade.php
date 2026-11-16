<article class="search-job-card">
    <div class="search-job-card-top">
        <img src="{{ asset('jobboard/images/'.$job['logo']) }}" alt="Logo minh họa {{ $job['company'] }}" class="search-job-logo" loading="lazy">
        <div class="search-job-identity">
            <h3>
                <a href="{{ route('jobs.show', $job['id']) }}">{{ $job['title'] }}</a>
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
        <a href="{{ route('jobs.show', $job['id']) }}" class="btn btn-outline-primary btn-sm">Xem chi tiết</a>
        <form action="{{ route('candidate.save', $job['id']) }}" method="post">
            @csrf
            <button type="submit" class="search-job-save" aria-label="{{ in_array($job['id'], $demo['saved']) ? 'Bỏ lưu' : 'Lưu việc' }} {{ $job['title'] }}" aria-pressed="{{ in_array($job['id'], $demo['saved']) ? 'true' : 'false' }}">
                <span class="{{ in_array($job['id'], $demo['saved']) ? 'icon-heart' : 'icon-heart-o' }}" aria-hidden="true"></span>
            </button>
        </form>
    </div>
</article>
