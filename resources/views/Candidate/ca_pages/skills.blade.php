@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            @if ($resume)
                <div class="p-4 border rounded">
                    <h2 class="h4">{{ $resume['name'] }}</h2>
                    <p>{{ $statusLabels[$resume['status']] }}</p>
                    @if ($resume['status'] === 'failed')
                        <div class="alert alert-danger">Phân tích thất bại (minh họa). Có thể nạp lại mẫu hoặc nhập kỹ năng thủ công.</div>
                    @endif
                    <form action="{{ route('candidate.resume-action', [$resume['id'], 'sample']) }}" method="post" class="mb-4">
                        @csrf
                        <button class="btn btn-light">Nạp kỹ năng mẫu — không trích xuất PDF</button>
                    </form>
                    <h3 class="h5">Kỹ năng nhận diện minh họa</h3>
                    <p>{{ implode(', ', $resume['recognized']) ?: 'Chưa có kết quả nhận diện.' }}</p>
                    <form action="{{ route('candidate.resume-action', [$resume['id'], 'skills']) }}" method="post">
                        @csrf
                        <label for="skills-editor">Kỹ năng chuẩn hóa (phân cách bằng dấu phẩy)</label>
                        <textarea id="skills-editor" name="skills" class="form-control mb-3" rows="4" maxlength="1500" required>{{ old('skills', implode(', ', $resume['skills'])) }}</textarea>
                        <div id="skill-tags" class="mb-3" aria-live="polite"></div>
                        <div class="input-group mb-3">
                            <input id="new-skill" class="form-control" aria-label="Kỹ năng mới" placeholder="Thêm kỹ năng, ví dụ Laravel" maxlength="50">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-light" id="add-skill">Thêm</button>
                            </div>
                        </div>
                        <p class="small">Chỉnh sửa trực tiếp hoặc nhấn × để xóa. Không chỉnh sửa nội dung PDF.</p>
                        <button class="btn btn-primary text-white">Xác nhận dữ liệu demo</button>
                    </form>
                </div>
            @else
                @include('Candidate.empty-state', ['emptyTitle' => 'Chưa chọn CV', 'emptyUrl' => route('candidate.resumes'), 'emptyAction' => 'Quản lý CV'])
            @endif
        </div>
    </section>
@endsection
