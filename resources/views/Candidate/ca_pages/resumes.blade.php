@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            <p>PDF được lưu riêng tư trên máy chủ chạy bản demo, chỉ truy cập qua phiên này. Dùng tệp mẫu để thử; có thể xóa toàn bộ tại Cài đặt tài khoản.</p>
            <form action="{{ route('candidate.upload') }}" method="post" enctype="multipart/form-data" class="p-4 rounded candidate-dropzone mb-5" id="resume-dropzone" data-loading="resume-loading">
                @csrf
                <h2 class="h4">Tải CV PDF</h2>
                <p>Kéo thả hoặc chọn tệp · Tối đa 5 MB · Tối đa 10 CV · Không có CV Builder.</p>
                <label for="resume-pdf">Chọn tệp PDF</label>
                <input type="file" id="resume-pdf" name="pdf" accept="application/pdf,.pdf" class="form-control-file mb-3" required>
                <p id="selected-file-name" class="small candidate-file-name"></p>
                <button class="btn btn-primary text-white">Tải CV lên</button>
                <div id="resume-loading" class="candidate-loading mt-3" role="status" hidden>
                    <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
                    Đang tải lên...
                </div>
            </form>
            <h2 class="h4 mb-4">CV của bạn ({{ count($demo['resumes']) }}/10)</h2>
            @forelse ($demo['resumes'] as $resume)
                @include('Candidate.resume-card')
            @empty
                @include('Candidate.empty-state', [
                    'emptyTitle' => 'Chưa có CV chính',
                    'emptyDescription' => 'Tải PDF đầu tiên để bắt đầu. Gợi ý AI đang tạm dừng.',
                ])
            @endforelse
        </div>
    </section>
@endsection
