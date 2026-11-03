@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            <p>Điểm phù hợp chỉ là chỉ số tham khảo, không phải xác suất trúng tuyển. Các điểm trên trang này là số liệu minh họa, chưa có mô hình AI xử lý CV.</p>
            <div class="bg-light p-4 border rounded mb-4">
                <h2 class="h5">CV chính: {{ $mainResume['name'] ?? 'Chưa có' }}</h2>
                <a class="btn btn-outline-primary" href="{{ route('candidate.resumes') }}">Thay đổi CV chính</a>
            </div>
            @if ($mainResume && $mainResume['status'] === 'completed')
                <form method="get" class="row mb-4">
                    <div class="col-md-5 form-group">
                        <label for="recommend-location">Địa điểm</label>
                        <select class="form-control" name="location" id="recommend-location">
                            <option value="">Tất cả</option>
                            @foreach ($filterOptions['location'][1] as $location)
                                <option @selected(request('location') === $location)>{{ $location }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5 form-group">
                        <label for="recommend-mode">Hình thức</label>
                        <select class="form-control" name="mode" id="recommend-mode">
                            <option value="">Tất cả</option>
                            @foreach ($filterOptions['mode'][1] as $mode)
                                <option @selected(request('mode') === $mode)>{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 align-self-end form-group">
                        <button class="btn btn-primary text-white">Lọc</button>
                    </div>
                </form>
                @forelse ($recommendations as $recommendation)
                    <div class="mb-4">
                        <ul class="job-listings mb-0">
                            @include('Candidate.job-row', ['job' => $recommendation['job']])
                        </ul>
                        <div class="bg-light border rounded p-4">
                            <h3 class="h5 text-primary">{{ $recommendation['score'] }}% phù hợp — minh họa</h3>
                            <progress class="candidate-progress mb-3" value="{{ $recommendation['score'] }}" max="100" aria-label="Điểm phù hợp minh họa"></progress>
                            <p>Kỹ năng đáp ứng: {{ implode(', ', $recommendation['matched']) ?: 'Chưa có' }}</p>
                            <p>Kỹ năng còn thiếu: {{ implode(', ', $recommendation['missing']) ?: 'Không có trong danh sách mẫu' }}</p>
                            <a class="btn btn-primary text-white" href="{{ route('jobs.show', $recommendation['job']['id']) }}">Chi tiết và ứng tuyển</a>
                        </div>
                    </div>
                @empty
                    @include('Candidate.empty-state', ['emptyTitle' => 'Không có gợi ý theo bộ lọc'])
                @endforelse
            @else
                @include('Candidate.empty-state', [
                    'emptyTitle' => 'Gợi ý AI đang tạm dừng',
                    'emptyDescription' => 'Cần CV chính hợp lệ đã xác nhận kỹ năng. Hãy tải PDF và xác nhận trước khi tiếp tục.',
                    'emptyUrl' => route('candidate.resumes'),
                    'emptyAction' => 'Hoàn thiện CV',
                ])
            @endif
        </div>
    </section>
@endsection
