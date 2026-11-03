<section class="jobs-discovery-section" aria-labelledby="suggested-jobs-title">
    <div class="container">
        <div class="jobs-results-heading">
            <div>
                <h2 id="suggested-jobs-title">
                    <span class="icon-lightbulb-o mr-2" aria-hidden="true"></span>
                    Công việc gợi ý
                </h2>
                <p>Khám phá cơ hội theo CV chính đã xác nhận kỹ năng.</p>
            </div>
            <a href="{{ route('candidate.recommendations') }}" class="jobs-saved-link">
                Xem tất cả gợi ý
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
                    <h3>Hoàn thiện CV để xem công việc gợi ý</h3>
                    <p>Gợi ý đang tạm dừng. Hãy tải CV PDF, chọn CV chính và xác nhận kỹ năng để trải nghiệm gợi ý minh họa.</p>
                    <a href="{{ route('candidate.resumes') }}" class="btn btn-primary">Hoàn thiện CV</a>
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
                <p>Khám phá công ty và các vị trí đang tuyển trong dữ liệu minh họa.</p>
            </div>
            <a href="{{ route('companies') }}" class="jobs-saved-link">
                Xem tất cả công ty
                <span class="icon-chevron-right ml-1" aria-hidden="true"></span>
            </a>
        </div>
        <div class="jobs-result-grid">
            @foreach ($featuredCompanies as $company)
                <article class="jobs-company-card">
                    <img src="{{ asset('jobboard/images/'.$company['logo']) }}" alt="Logo minh họa {{ $company['name'] }}" loading="lazy">
                    <h3>{{ $company['name'] }}</h3>
                    <p class="jobs-company-location">
                        <span class="icon-map-marker mr-1" aria-hidden="true"></span>
                        {{ $company['locations'] }}
                    </p>
                    <p class="jobs-company-openings">{{ $company['openings'] }} vị trí đang tuyển</p>
                    <a href="{{ route('jobs', ['q' => $company['name']]) }}" class="btn btn-outline-primary btn-sm">Xem việc làm</a>
                </article>
            @endforeach
        </div>
    </div>
</section>
