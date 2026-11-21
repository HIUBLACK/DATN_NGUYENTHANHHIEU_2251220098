@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <form action="{{ route('candidate.upload') }}" method="post" enctype="multipart/form-data" class="p-4 rounded candidate-dropzone mb-5" id="resume-dropzone">
                @csrf
                <h2 class="h4">Tải CV PDF</h2>
                <p>PDF riêng tư · Tối đa 5 MB/tệp · Tối đa 10 CV. CV đầu tiên tự động là CV chính.</p>
                <label for="resume-pdf">Chọn tệp PDF <span class="text-danger">*</span></label>
                <input type="file" id="resume-pdf" name="pdf" accept="application/pdf,.pdf" class="form-control-file mb-3" required>
                <p id="selected-file-name" class="small"></p>
                <button type="submit" class="btn btn-primary text-white">Tải CV lên</button>
            </form>
            <h2 class="h4 mb-4">CV của bạn ({{ $resumes->count() }}/10)</h2>
            @forelse ($resumes as $resume)
                <article class="p-4 border rounded mb-4">
                    <h3 class="h5">
                        {{ $resume->display_name }}
                        @if ($resume->is_primary)
                            <span class="badge badge-primary">CV chính</span>
                        @endif
                    </h3>
                    <p>Phiên bản {{ $resume->current_revision }} · Cập nhật {{ \App\Support\DisplayDate::format($resume->updated_at) }}</p>
                    <div class="d-flex flex-wrap mb-3">
                        <a href="{{ route('candidate.pdf', $resume->public_id) }}" target="_blank" rel="noopener" class="btn btn-outline-primary mr-2 mb-2">Xem PDF</a>
                        <a href="{{ route('candidate.pdf', [$resume->public_id, 'download' => 1]) }}" class="btn btn-outline-primary mr-2 mb-2">Tải xuống</a>
                        @unless ($resume->is_primary)
                            <form action="{{ route('candidate.resume-action', [$resume->public_id, 'main']) }}" method="post">
                                @csrf
                                <button class="btn btn-outline-primary mb-2" type="submit">Đặt làm CV chính</button>
                            </form>
                        @endunless
                    </div>
                    <details class="mb-3">
                        <summary>Đổi tên / thay PDF</summary>
                        <form method="post" action="{{ route('candidate.resume-action', [$resume->public_id, 'rename']) }}" class="mt-3">
                            @csrf
                            <label for="resume-name-{{ $resume->id }}">Tên CV</label>
                            <input id="resume-name-{{ $resume->id }}" name="name" value="{{ $resume->display_name }}" maxlength="100" required class="form-control mb-2">
                            <button type="submit" class="btn btn-outline-primary">Lưu tên</button>
                        </form>
                        <form method="post" enctype="multipart/form-data" action="{{ route('candidate.replace', $resume->public_id) }}" class="mt-3">
                            @csrf
                            <label for="replace-{{ $resume->id }}">PDF mới (không thay đổi CV đã nộp)</label>
                            <input id="replace-{{ $resume->id }}" name="pdf" type="file" accept="application/pdf,.pdf" required class="form-control-file mb-2">
                            <button type="submit" class="btn btn-outline-primary">Thay PDF</button>
                        </form>
                    </details>
                    <form action="{{ route('candidate.resume-action', [$resume->public_id, 'delete']) }}" method="post" data-confirm="Xóa CV này khỏi danh sách? CV trong các hồ sơ đã nộp vẫn được giữ nguyên.">
                        @csrf
                        @if ($resume->is_primary && $resumes->count() > 1)
                            <label for="replacement-{{ $resume->id }}">Chọn CV chính thay thế</label>
                            <select id="replacement-{{ $resume->id }}" name="replacement" class="form-control mb-2" required>
                                <option value="">Chọn CV</option>
                                @foreach ($resumes as $replacement)
                                    @if ($replacement->id !== $resume->id)
                                        <option value="{{ $replacement->public_id }}">{{ $replacement->display_name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        @endif
                        <button type="submit" class="btn btn-outline-danger">Xóa CV</button>
                    </form>
                </article>
            @empty
                @include('Candidate.empty-state', ['emptyTitle' => 'Chưa có CV', 'emptyDescription' => 'Tải CV PDF để ứng tuyển.'])
            @endforelse
        </div>
    </section>
@endsection
