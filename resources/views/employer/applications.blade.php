@extends('employer.layout')

@section('content')
    <div class="card p-4 mb-4">
        <form id="application-filters" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="candidate-search" class="form-label-custom">Tìm theo tên ứng viên</label>
                <input id="candidate-search" name="q" class="form-control-custom" placeholder="Nhập họ tên">
            </div>
            <div class="col-md-3">
                <label for="application-status" class="form-label-custom">Trạng thái hồ sơ</label>
                <select id="application-status" name="status" class="form-select-custom" data-options="application"></select>
            </div>
            <div class="col-md-3">
                <label for="application-job" class="form-label-custom">Tin tuyển dụng</label>
                <select id="application-job" name="jobId" class="form-select-custom"></select>
            </div>
            <div class="col-md-3">
                <label for="application-ai-status" class="form-label-custom">Đánh giá AI</label>
                <select id="application-ai-status" name="aiStatus" class="form-select-custom">
                    <option value="">Tất cả đánh giá</option>
                    <option value="completed">Đã đánh giá</option>
                    <option value="unrated">Chưa có kết quả</option>
                    <option value="pending">Chưa đánh giá</option>
                    <option value="processing">Đang đánh giá</option>
                    <option value="failed">Đánh giá thất bại</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="application-sort" class="form-label-custom">Sắp xếp</label>
                <select id="application-sort" name="sort" class="form-select-custom">
                    <option value="">Thứ tự mặc định</option>
                    <option value="ai-desc">Điểm AI cao nhất</option>
                    <option value="ai-asc">Điểm AI thấp nhất</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="reset" class="btn btn-light">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                    Xóa tìm kiếm
                </button>
            </div>
        </form>
    </div>
    <p class="small text-muted-green">Điểm AI là dữ liệu minh họa về độ phù hợp CV–JD, không phải xác suất được tuyển. Quyết định tuyển dụng thuộc về nhà tuyển dụng.</p>
    <p id="application-count"></p>
    <div class="table-card-custom">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th scope="col" class="recruiter-order">STT</th>
                        <th>Ứng viên / Vị trí</th>
                        <th>Ngày ứng tuyển</th>
                        <th>Trạng thái</th>
                        <th>Điểm AI / Đánh giá</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="application-table-body"></tbody>
            </table>
        </div>
    </div>
@endsection
