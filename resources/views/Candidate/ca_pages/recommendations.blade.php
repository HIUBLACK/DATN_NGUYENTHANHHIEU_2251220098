@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <p>Điểm phù hợp chỉ là chỉ số tham khảo, không phải xác suất trúng tuyển.</p>
            <p class="small text-muted">Mỗi lượt xét tối đa {{ max(1, min(20, (int) config('ai.recommendation.max_jobs'))) }} tin công khai mới nhất. Tin chưa đủ dữ liệu AI chưa có điểm đề xuất.</p>
            <div class="bg-light p-4 border rounded mb-4">
                <h2 class="h5">CV chính: {{ $recommendationResume ?? 'Chưa có' }}</h2>
                <a class="btn btn-outline-primary" href="{{ route('candidate.resumes') }}">Thay đổi CV chính</a>
                <form method="post" action="{{ route('candidate.recommendations.refresh') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-outline-primary" type="submit">Kiểm tra lại đề xuất</button>
                </form>
            </div>
            @if ($recommendationState === 'completed')
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
                            <h3 class="h5 text-primary">{{ $recommendation['score'] }}/100 phù hợp</h3>
                            <progress class="candidate-progress mb-3" value="{{ $recommendation['score'] }}" max="100" aria-label="Điểm phù hợp AI"></progress>
                            <p>Ngữ nghĩa: {{ $recommendation['semantic_score'] }}/100 · Kỹ năng: {{ $recommendation['skill_score'] }}/100</p>
                            <p>Kỹ năng đáp ứng: {{ implode(', ', $recommendation['matched']) ?: 'Chưa có' }}</p>
                            <p>Kỹ năng bắt buộc chưa tìm thấy bằng chứng: {{ implode(', ', $recommendation['missing']) ?: 'Không có' }}</p>
                            <p>{{ $recommendation['explanation'] }}</p>
                            <a class="btn btn-primary text-white" href="{{ route('jobs.show', $recommendation['job']['id']) }}">Chi tiết và ứng tuyển</a>
                        </div>
                    </div>
                @empty
                    @include('Candidate.empty-state', ['emptyTitle' => 'Chưa có tin đủ dữ liệu phù hợp với bộ lọc'])
                @endforelse
                {{ $recommendations->links() }}
            @else
                @include('Candidate.empty-state', [
                    'emptyTitle' => $recommendationMessage,
                    'emptyDescription' => 'Bạn có thể tải lại trang hoặc bấm Kiểm tra lại đề xuất. Thử lại tác vụ lỗi sau ít nhất 60 giây.',
                    'emptyUrl' => route('candidate.resumes'),
                    'emptyAction' => 'Hoàn thiện CV',
                ])
            @endif
        </div>
    </section>
@endsection
