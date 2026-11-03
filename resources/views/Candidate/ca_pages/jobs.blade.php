@extends('Candidate.master')

@section('content')
    <section class="jobs-search-section" aria-labelledby="jobs-title">
        <div class="container">
            <h1 id="jobs-title">Tìm việc làm phù hợp với bạn</h1>
            <p class="jobs-search-intro">Khám phá cơ hội theo kỹ năng, địa điểm và định hướng nghề nghiệp.</p>
            <form method="get" action="{{ route('jobs') }}" class="jobs-search-form">
                <div class="jobs-search-bar">
                    <div class="jobs-search-field">
                        <label for="job-keyword" class="sr-only">Từ khóa</label>
                        <span class="icon-search" aria-hidden="true"></span>
                        <input id="job-keyword" name="q" value="{{ request('q') }}" class="form-control" placeholder="Vị trí, kỹ năng hoặc tên công ty" maxlength="100">
                    </div>
                    <div class="jobs-search-field jobs-search-location">
                        <label for="filter-location" class="sr-only">Địa điểm</label>
                        <span class="icon-map-marker" aria-hidden="true"></span>
                        <select id="filter-location" name="location" class="form-control">
                            <option value="">Tất cả địa điểm</option>
                            @foreach ($filterOptions['location'][1] as $location)
                                <option @selected(request('location') === $location)>{{ $location }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary jobs-search-submit" type="submit">
                        <span class="icon-search mr-2" aria-hidden="true"></span>
                        Tìm kiếm
                    </button>
                </div>
                <div class="jobs-filter-panel">
                    <div class="jobs-filter-heading">
                        <span class="icon-filter mr-2" aria-hidden="true"></span>
                        Lọc công việc
                    </div>
                    <div class="row">
                        @foreach ($filterOptions as $field => [$label, $options])
                            @if ($field !== 'location')
                                <div class="col-sm-6 col-lg-3 form-group">
                                    <label for="filter-{{ $field }}">{{ $label }}</label>
                                    <select id="filter-{{ $field }}" name="{{ $field }}" class="form-control">
                                        <option value="">Tất cả</option>
                                        @foreach ($options as $option)
                                            <option @selected(request($field) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        @endforeach
                        <div class="col-sm-6 col-lg-3 form-group">
                            <label for="salary">Mức lương tối thiểu</label>
                            <select id="salary" name="salary" class="form-control">
                                <option value="">Tất cả mức lương</option>
                                @foreach ([15, 25, 35, 45] as $salary)
                                    <option value="{{ $salary }}" @selected((int) request('salary') === $salary)>
                                        Từ {{ $salary }} triệu/tháng
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="jobs-filter-actions">
                        <a href="{{ route('jobs') }}">Xóa bộ lọc</a>
                        <button class="btn btn-outline-primary btn-sm" type="submit">Áp dụng bộ lọc</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
    <section class="jobs-results-section" aria-labelledby="jobs-results-title">
        <div class="container">
            <div class="jobs-results-heading">
                <div>
                    <h2 id="jobs-results-title">Cơ hội việc làm dành cho bạn</h2>
                    <p>Tìm thấy <strong>{{ $jobs->total() }}</strong> công việc phù hợp</p>
                </div>
                <a href="{{ route('candidate.saved') }}" class="jobs-saved-link">
                    <span class="icon-heart-o mr-2" aria-hidden="true"></span>
                    Việc làm đã lưu
                </a>
            </div>
            <p class="jobs-demo-note">
                <span class="icon-info-circle mr-2" aria-hidden="true"></span>
                Dữ liệu minh họa. Công ty và công việc chưa kết nối dữ liệu tuyển dụng thực tế.
            </p>
            @if ($jobs->isEmpty())
                @include('Candidate.empty-state', [
                    'emptyTitle' => 'Không tìm thấy kết quả',
                    'emptyDescription' => 'Thử đổi từ khóa hoặc mở rộng bộ lọc.',
                    'emptyUrl' => route('jobs'),
                    'emptyAction' => 'Xóa bộ lọc',
                ])
            @else
                <div class="jobs-result-grid">
                    @foreach ($jobs as $job)
                        @include('Candidate.search-job-card')
                    @endforeach
                </div>
                <nav class="custom-pagination jobs-pagination" aria-label="Phân trang">
                    @if ($jobs->previousPageUrl())
                        <a href="{{ $jobs->previousPageUrl() }}" aria-label="Trang trước">
                            <span class="icon-chevron-left" aria-hidden="true"></span>
                        </a>
                    @endif
                    @for ($number = 1; $number <= $jobs->lastPage(); $number++)
                        <a href="{{ $jobs->url($number) }}" @class(['active' => $number === $jobs->currentPage()]) aria-label="Trang {{ $number }}" @if ($number === $jobs->currentPage()) aria-current="page" @endif>
                            {{ $number }}
                        </a>
                    @endfor
                    @if ($jobs->nextPageUrl())
                        <a href="{{ $jobs->nextPageUrl() }}" aria-label="Trang tiếp">
                            <span class="icon-chevron-right" aria-hidden="true"></span>
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
    @include('Candidate.jobs-discovery')
@endsection
