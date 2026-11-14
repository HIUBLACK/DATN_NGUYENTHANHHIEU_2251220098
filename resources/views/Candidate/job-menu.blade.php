<li class="job-mega">
    <a href="{{ route('jobs') }}" class="job-mega-link">Việc làm</a>
    <button type="button" class="job-mega-toggle" aria-label="Mở danh mục việc làm" aria-expanded="false">
        <span class="icon-chevron-down" aria-hidden="true"></span>
    </button>
    <div class="job-mega-panel" hidden>
        <div class="job-mega-categories">
            @foreach ($jobMenuGroups as $field => $label)
                <div class="job-mega-category" data-job-category="{{ $field }}">
                    <a href="{{ route('jobs', ['browse' => $field]) }}#filter-{{ $field }}">Việc làm theo {{ $label }}</a>
                    <button type="button" data-job-expand="{{ $field }}" aria-label="Xem danh sách {{ $label }}" aria-expanded="false">
                        <span class="icon-chevron-right" aria-hidden="true"></span>
                    </button>
                </div>
            @endforeach
        </div>
        <div class="job-mega-results">
            <p class="job-mega-title"></p>
            <div class="job-mega-options"></div>
        </div>
    </div>
</li>
