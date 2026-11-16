@extends('employer.layout')

@section('content')
    <div class="card p-4 mb-4">
        <form id="job-filters" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="job-search" class="form-label-custom">Tìm tiêu đề</label>
                <input id="job-search" name="q" class="form-control-custom" placeholder="Nhập tên công việc">
            </div>
            <div class="col-md-3">
                <label for="job-activity" class="form-label-custom">Hoạt động</label>
                <select id="job-activity" name="activity" class="form-select-custom" data-options="activity"></select>
            </div>
            <div class="col-md-3">
                <label for="job-moderation" class="form-label-custom">Kiểm duyệt</label>
                <select id="job-moderation" name="moderation" class="form-select-custom" data-options="moderation"></select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-light" type="reset" aria-label="Xóa bộ lọc">
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                </button>
            </div>
        </form>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="mb-0" id="job-count"></p>
        <a href="{{ route('recruiter.job-create') }}" class="btn-dark-custom">
            <i class="bi bi-plus-circle" aria-hidden="true"></i>
            Tạo tin mới
        </a>
    </div>
    <div class="table-card-custom">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th scope="col" class="recruiter-order">STT</th>
                        <th>Tiêu đề / Ngày tạo</th>
                        <th>Hạn ứng tuyển</th>
                        <th>Hoạt động</th>
                        <th>Kiểm duyệt</th>
                        <th>Hồ sơ</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="job-table-body"></tbody>
            </table>
        </div>
    </div>
    <nav id="job-pagination" class="d-flex gap-2 mt-3" aria-label="Phân trang tin tuyển dụng"></nav>
@endsection
