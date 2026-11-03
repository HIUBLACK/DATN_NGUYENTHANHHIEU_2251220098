@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            <form method="get" action="{{ route('jobs') }}" class="search-jobs-form mb-5">
                <div class="row">
                    <div class="col-lg-9 mb-3">
                        <label for="job-keyword" class="sr-only">Từ khóa</label>
                        <input id="job-keyword" name="q" value="{{ request('q') }}" class="form-control form-control-lg" placeholder="Vị trí, kỹ năng, công ty...">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <button class="btn btn-primary btn-lg btn-block text-white">Tìm việc làm</button>
                    </div>
                </div>
                <div class="row">
                    @foreach ($filterOptions as $field => [$label, $options])
                        <div class="col-md-6 col-lg-3 form-group">
                            <label for="filter-{{ $field }}">{{ $label }}</label>
                            <select id="filter-{{ $field }}" name="{{ $field }}" class="form-control">
                                <option value="">Tất cả</option>
                                @foreach ($options as $option)
                                    <option @selected(request($field) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="col-md-6 col-lg-3 form-group">
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
                    <div class="col-md-6 col-lg-3 align-self-end form-group">
                        <a href="{{ route('jobs') }}" class="btn btn-light">Xóa bộ lọc</a>
                    </div>
                </div>
            </form>
            <h2 class="section-title mb-4">{{ $jobs->total() }} công việc phù hợp</h2>
            @if ($jobs->isEmpty())
                @include('Candidate.empty-state', ['emptyTitle' => 'Không tìm thấy kết quả', 'emptyDescription' => 'Thử đổi từ khóa hoặc mở rộng bộ lọc.'])
            @else
                <ul class="job-listings mb-5">
                    @foreach ($jobs as $job)
                        @include('Candidate.job-row')
                    @endforeach
                </ul>
                <nav class="custom-pagination ml-auto" aria-label="Phân trang">
                    <div class="d-inline-block">
                        @for ($number = 1; $number <= $jobs->lastPage(); $number++)
                            <a href="{{ $jobs->url($number) }}" @class(['active' => $number === $jobs->currentPage()]) aria-label="Trang {{ $number }}">
                                {{ $number }}
                            </a>
                        @endfor
                    </div>
                </nav>
            @endif
        </div>
    </section>
@endsection
