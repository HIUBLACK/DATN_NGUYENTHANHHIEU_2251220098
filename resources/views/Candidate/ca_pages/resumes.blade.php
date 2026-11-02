@extends('Candidate.layout')
@section('title','Quản lý CV PDF')
@section('candidate-content')
<div class="workspace-heading"><div><span class="eyebrow">ĐỂ KỸ NĂNG LÊN TIẾNG</span><h1>Hồ sơ của bạn</h1><p>Quản lý CV PDF và chọn hồ sơ phù hợp cho mỗi cơ hội.</p></div><span class="status" id="resume-count">0 / 10 CV</span></div>
<x-alert>PDF được lưu cục bộ trong trình duyệt này, không tải lên máy chủ. Phân tích AI chưa kết nối; bạn có thể thử các trạng thái minh họa.</x-alert>
<div class="upload-zone" id="upload-zone"><i class="far fa-file-pdf" aria-hidden="true"></i><h3>Kéo thả CV PDF vào đây</h3><p>Chỉ nhận PDF · Tối đa 5 MB/tệp · Tối đa 10 CV</p><label class="button" for="resume-upload">Chọn tệp PDF <span aria-hidden="true">↑</span></label><input class="visually-hidden" type="file" id="resume-upload" accept="application/pdf,.pdf" multiple><p class="small mt-3 mb-0">Không chỉnh sửa nội dung PDF trên hệ thống.</p></div>
<div id="upload-error" class="notice notice-error" role="alert" hidden></div><x-loading-state id="upload-loading" hidden/>
<section id="resume-list" aria-label="Danh sách CV" aria-live="polite"></section>
<input type="file" id="resume-replace" accept="application/pdf,.pdf" class="visually-hidden" aria-label="Chọn PDF thay thế">
<div class="panel mt-4"><h3>Các trạng thái phân tích</h3><div class="skill-list">@foreach(['pending','processing','needs_review','completed','failed'] as $status)<x-status-badge :status="$status"/>@endforeach</div><p class="small mt-3 mb-0">CV chính cần được xác nhận kỹ năng trước khi hiển thị gợi ý AI. PDF hợp lệ vẫn có thể dùng để ứng tuyển trong khi chờ phân tích.</p></div>
@endsection
