<article class="p-4 border rounded mb-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between">
        <div>
            <h3 class="h5">{{ $resume['name'] }}</h3>
            <p class="small candidate-file-name">{{ $resume['filename'] }} · {{ $resume['created_at'] }}</p>
        </div>
        <div>
            @if ($resume['main'])
                <span class="badge badge-success">CV chính</span>
            @endif
            <span class="badge badge-light border">{{ $statusLabels[$resume['status']] }}</span>
        </div>
    </div>
    @if ($resume['status'] === 'processing')
        <p role="status">
            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
            Đang phân tích (trạng thái minh họa).
        </p>
    @elseif ($resume['status'] === 'failed')
        <p class="text-danger">Phân tích thất bại — trạng thái mẫu. Bạn có thể thử lại hoặc nhập kỹ năng.</p>
    @endif
    <div class="candidate-actions mb-3">
        <a class="btn btn-light" href="{{ route('candidate.pdf', $resume['id']) }}" target="_blank" rel="noopener">Xem PDF</a>
        <a class="btn btn-light" href="{{ route('candidate.pdf', ['id' => $resume['id'], 'download' => 1]) }}">Tải xuống</a>
        <a class="btn btn-outline-primary" href="{{ route('candidate.skills', ['cv' => $resume['id']]) }}">Xác nhận kỹ năng</a>
    </div>
    @unless ($resume['main'])
        <form method="post" action="{{ route('candidate.resume-action', [$resume['id'], 'main']) }}" class="mb-3">
            @csrf
            <button class="btn btn-primary text-white">Đặt làm CV chính</button>
        </form>
    @endunless
    <details>
        <summary>Đổi tên, thay PDF và tùy chọn khác</summary>
        <div class="row mt-3">
            <div class="col-lg-6 mb-4">
                <form action="{{ route('candidate.resume-action', [$resume['id'], 'rename']) }}" method="post">
                    @csrf
                    <label for="resume-name-{{ $resume['id'] }}">Tên CV</label>
                    <input class="form-control mb-2" id="resume-name-{{ $resume['id'] }}" name="name" value="{{ $resume['name'] }}" maxlength="100" required>
                    <button class="btn btn-light">Đổi tên</button>
                </form>
            </div>
            <div class="col-lg-6 mb-4">
                <form action="{{ route('candidate.replace', $resume['id']) }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <label for="replace-{{ $resume['id'] }}">Thay PDF (sẽ chờ phân tích lại)</label>
                    <input type="file" class="form-control-file mb-2" id="replace-{{ $resume['id'] }}" name="pdf" accept="application/pdf,.pdf" required>
                    <button class="btn btn-light">Thay thế PDF</button>
                </form>
            </div>
            <div class="col-lg-6 mb-4">
                <form action="{{ route('candidate.resume-action', [$resume['id'], 'state']) }}" method="post">
                    @csrf
                    <label for="resume-status-{{ $resume['id'] }}">Thử trạng thái phân tích (không chạy AI)</label>
                    <select class="form-control mb-2" id="resume-status-{{ $resume['id'] }}" name="status">
                        @foreach (['pending', 'processing', 'needs_review', 'failed'] as $status)
                            <option value="{{ $status }}" @selected($status === $resume['status'])>{{ $statusLabels[$status] }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-light">Xem trạng thái mẫu</button>
                </form>
            </div>
            <div class="col-lg-6 mb-4">
                <form action="{{ route('candidate.resume-action', [$resume['id'], 'delete']) }}" method="post" data-confirm="Xóa CV này? Nếu là CV cuối cùng, gợi ý AI sẽ tạm dừng.">
                    @csrf
                    @if ($resume['main'] && count($demo['resumes']) > 1)
                        <label for="replacement-{{ $resume['id'] }}">CV chính thay thế</label>
                        <select class="form-control mb-2" id="replacement-{{ $resume['id'] }}" name="replacement" required>
                            <option value="">Chọn CV thay thế trước khi xóa</option>
                            @foreach ($demo['resumes'] as $replacement)
                                @if ($replacement['id'] !== $resume['id'])
                                    <option value="{{ $replacement['id'] }}">{{ $replacement['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    @endif
                    <button class="btn btn-outline-danger">Xóa CV</button>
                </form>
            </div>
        </div>
    </details>
</article>
