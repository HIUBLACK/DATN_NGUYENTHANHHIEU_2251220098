<section class="jobs-discovery-section" aria-labelledby="suggested-jobs-title">
    <div class="container">
        <div class="jobs-results-heading">
            <div>
                <h2 id="suggested-jobs-title">
                    <span class="icon-lightbulb-o mr-2" aria-hidden="true"></span>
                    Công việc gợi ý
                </h2>
                <p>Gợi ý AI cho tin tuyển dụng thật sẽ được triển khai ở giai đoạn sau.</p>
            </div>
            <a href="{{ route('candidate.recommendations') }}" class="jobs-saved-link">
                Trải nghiệm gợi ý demo
                <span class="icon-chevron-right ml-1" aria-hidden="true"></span>
            </a>
        </div>
        @if (count($suggestedJobs) > 0)
            <p class="jobs-demo-note">Gợi ý và điểm phù hợp là minh họa, chưa chạy AI; không phải xác suất trúng tuyển.</p>
            <div class="jobs-result-grid">
                @foreach ($suggestedJobs as $suggestion)
                    <div class="jobs-suggestion">
                        <p class="jobs-suggestion-score">
                            <span class="icon-check-circle mr-2" aria-hidden="true"></span>
                            {{ $suggestion['score'] }}% phù hợp · Minh họa
                        </p>
                        @include('Candidate.search-job-card', ['job' => $suggestion['job']])
                    </div>
                @endforeach
            </div>
        @else
            <div class="jobs-suggestion-empty">
                <span class="icon-file-text jobs-suggestion-empty-icon" aria-hidden="true"></span>
                <div>
                    <h3>Chưa có gợi ý AI cho tin thật</h3>
                    <p>Bạn có thể dùng bộ lọc ở trên để tìm việc. Khu vực trải nghiệm AI demo được tách riêng.</p>
                </div>
            </div>
        @endif
    </div>
</section>

<section class="jobs-discovery-section jobs-companies-section" aria-labelledby="featured-companies-title">
    <div class="container">
        <div class="jobs-results-heading">
            <div>
                <h2 id="featured-companies-title">
                    <span class="icon-building mr-2" aria-hidden="true"></span>
                    Các công ty nổi bật
                </h2>
                <p>Khám phá doanh nghiệp có tin tuyển dụng đang công khai.</p>
            </div>
            <a href="{{ route('companies') }}" class="jobs-saved-link">
                Xem tất cả công ty
                <span class="icon-chevron-right ml-1" aria-hidden="true"></span>
            </a>
        </div>
        <div class="jobs-result-grid">
            @forelse ($featuredCompanies as $company)
                <article class="jobs-company-card">
                    @if ($company['logo_url'])
                        <img src="{{ $company['logo_url'] }}" alt="Logo {{ $company['name'] }}" loading="lazy">
                    @endif
                    <h3>{{ $company['name'] }}</h3>
                    <p class="jobs-company-location">
                        <span class="icon-map-marker mr-1" aria-hidden="true"></span>
                        {{ $company['locations'] }}
                    </p>
                    <p class="jobs-company-openings">{{ $company['openings'] }} vị trí đang tuyển</p>
                    <a href="{{ route('jobs', ['q' => $company['name']]) }}" class="btn btn-outline-primary btn-sm">Xem việc làm</a>
                </article>
            @empty
                <p>Chưa có doanh nghiệp có tin đủ điều kiện công khai.</p>
            @endforelse
        </div>
    </div>
</section>
