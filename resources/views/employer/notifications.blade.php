@extends('employer.layout')

@section('content')
    <div class="d-flex flex-wrap gap-2 mb-4">
        <label for="notification-filter" class="visually-hidden">Lọc thông báo</label>
        <select id="notification-filter" class="form-select recruiter-filter-select">
            <option value="all">Tất cả thông báo</option>
            <option value="unread">Chưa đọc</option>
            <option value="application">Hồ sơ mới</option>
            <option value="job">Kết quả kiểm duyệt</option>
        </select>
        <button type="button" id="read-all-notifications" class="btn-dark-custom">Đánh dấu tất cả đã đọc</button>
    </div>
    <div class="card" id="recruiter-notifications"></div>
@endsection
