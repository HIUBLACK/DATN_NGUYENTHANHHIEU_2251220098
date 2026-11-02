<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="VIEJOB AI — Khám phá cơ hội nghề nghiệp công nghệ thông tin tại Việt Nam.">
    <title>@yield('title', 'Việc làm công nghệ dành cho bạn') · VIEJOB AI</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/platform.css') }}?v=1">
    <script src="{{ asset('assets/js/platform-domain.js') }}?v=1" defer></script>
    <script src="{{ asset('assets/js/platform.js') }}?v=1" defer></script>
</head>
<body class="@yield('body-class')">
<a href="#main" class="skip-link">Chuyển đến nội dung chính</a>
@include('partials.header')
<div class="demo-strip"><span class="demo-dot"></span> Bản trải nghiệm giao diện · Dữ liệu minh họa, chưa kết nối tuyển dụng hoặc AI thực tế.</div>
@yield('shell')
@hasSection('shell') @else <main id="main" tabindex="-1">@yield('content')</main> @endif
@include('partials.footer')
<div id="toast" class="toast-message" role="status" aria-live="polite" hidden></div>
<dialog id="confirm-modal" class="app-dialog" aria-labelledby="confirm-title">
    <form method="dialog"><button class="icon-button dialog-close" value="cancel" aria-label="Đóng">×</button>
    <span class="eyebrow">XÁC NHẬN THAO TÁC</span><h2 id="confirm-title">Bạn muốn tiếp tục?</h2><p id="confirm-description"></p>
    <div id="confirm-extra"></div><div class="actions"><button class="button secondary" value="cancel">Hủy bỏ</button><button class="button danger" value="confirm">Xác nhận</button></div></form>
</dialog>
<dialog id="apply-modal" class="app-dialog" aria-labelledby="apply-title">
    <button type="button" class="icon-button dialog-close" data-close-dialog aria-label="Đóng">×</button>
    <span class="eyebrow">BƯỚC TIẾP THEO CỦA BẠN</span><h2 id="apply-title">Ứng tuyển công việc</h2>
    <p id="apply-job"></p><div class="notice">Thao tác demo trên trình duyệt. Hồ sơ không được gửi đến nhà tuyển dụng.</div>
    <form id="apply-form"><label for="apply-cv">Chọn CV PDF <span aria-hidden="true">*</span></label><select id="apply-cv" required></select>
    <p class="muted small">Có thể dùng PDF hợp lệ chưa hoàn tất phân tích AI.</p><a href="{{ route('candidate.resumes') }}">Quản lý hoặc tải CV mới</a>
    <label for="cover-letter">Thư giới thiệu (không bắt buộc)</label><textarea id="cover-letter" maxlength="2000" rows="4" placeholder="Chia sẻ vì sao bạn phù hợp với vị trí này..."></textarea>
    <label class="check-label"><input type="checkbox" required> Tôi đã kiểm tra CV và xác nhận nộp hồ sơ demo.</label>
    <p class="form-error" id="apply-error" role="alert"></p><button class="button" type="submit">Xác nhận ứng tuyển demo <span aria-hidden="true">↗</span></button></form>
</dialog>
<script type="application/json" id="job-data">{!! json_encode($allJobs ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@stack('scripts')
</body>
</html>
