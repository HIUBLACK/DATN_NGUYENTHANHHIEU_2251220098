@extends('employer.layout')

@section('content')
    <div id="job-editor" class="card p-4">
        <div id="job-version-warning" class="alert alert-warning" hidden>Nội dung mới phải được Admin duyệt lại. Bản đang công khai được giữ riêng; lưu hoặc gửi duyệt không công khai thay đổi ngay.</div>
        <form id="job-form" novalidate>
            <p class="small text-muted-green">Các trường có * là bắt buộc khi gửi duyệt. Bản nháp chỉ yêu cầu tiêu đề.</p>
            <div class="row g-3">
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="occupationId" class="form-label-custom">Nhóm nghề CNTT *</label>
                    <select id="occupationId" name="occupationId" class="form-select-custom" required></select>
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="specialtyId" class="form-label-custom">Chuyên môn CNTT *</label>
                    <select id="specialtyId" name="specialtyId" class="form-select-custom" required></select>
                    <p class="small text-muted-green mt-2">Chọn nhóm nghề trước. Giá trị đã lưu bị ẩn vẫn được giữ cho tin cũ.</p>
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="title" class="form-label-custom">Tiêu đề tuyển dụng *</label>
                    <input id="title" name="title" type="text" class="form-control-custom" required maxlength="150">
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="position" class="form-label-custom">Vị trí công việc CNTT *</label>
                    <input id="position" name="position" type="text" class="form-control-custom" required maxlength="100">
                </div>
                <div class="col-12  form-group-custom">
                    <label for="description" class="form-label-custom">Mô tả công việc *</label>
                    <textarea id="description" name="description" class="form-control-custom" rows="5" required maxlength="10000"></textarea>
                </div>
                <div class="col-12  form-group-custom">
                    <label for="requirements" class="form-label-custom">Yêu cầu ứng viên *</label>
                    <textarea id="requirements" name="requirements" class="form-control-custom" rows="5" required maxlength="10000"></textarea>
                </div>
                <div class="col-12  form-group-custom">
                    <label for="benefits" class="form-label-custom">Quyền lợi *</label>
                    <textarea id="benefits" name="benefits" class="form-control-custom" rows="5" required maxlength="10000"></textarea>
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="salary_min" class="form-label-custom">Lương tối thiểu (triệu/tháng) *</label>
                    <input id="salary_min" name="salary_min" type="number" class="form-control-custom" required min="0" max="1000" step="0.5">
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="salary_max" class="form-label-custom">Lương tối đa (triệu/tháng) *</label>
                    <input id="salary_max" name="salary_max" type="number" class="form-control-custom" required min="0" max="1000" step="0.5">
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="location" class="form-label-custom">Địa điểm *</label>
                    <input id="location" name="location" type="text" class="form-control-custom" required maxlength="150">
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="quantity" class="form-label-custom">Số lượng tuyển *</label>
                    <input id="quantity" name="quantity" type="number" class="form-control-custom" required min="1" max="1000" step="1">
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="experience" class="form-label-custom">Kinh nghiệm *</label>
                    <input id="experience" name="experience" type="text" class="form-control-custom" required maxlength="100">
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="skills" class="form-label-custom">Kỹ năng CNTT (phân cách bằng dấu phẩy) *</label>
                    <input id="skills" name="skills" type="text" class="form-control-custom" required maxlength="500">
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="deadline" class="form-label-custom">Hạn ứng tuyển *</label>
                    <input id="deadline" name="deadline" type="date" class="form-control-custom" required >
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="mode" class="form-label-custom">Hình thức làm việc *</label>
                    <select name="mode" id="mode" class="form-select-custom" required>
                        <option value="Onsite">Tại văn phòng (Onsite)</option>
                        <option value="Hybrid">Kết hợp (Hybrid)</option>
                        <option value="Remote">Từ xa (Remote)</option>
                    </select>
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="type" class="form-label-custom">Loại công việc *</label>
                    <select name="type" id="type" class="form-select-custom" required>
                        <option value="Toàn thời gian">Toàn thời gian</option>
                        <option value="Bán thời gian">Bán thời gian</option>
                        <option value="Thực tập">Thực tập</option>
                        <option value="Hợp đồng">Hợp đồng</option>
                    </select>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" name="intent" value="draft" class="btn btn-light">Lưu bản nháp (demo)</button>
                <button type="button" id="preview-job" class="btn btn-outline-dark">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i>
                    Xem trước
                </button>
                <button type="submit" name="intent" value="submit" class="btn-dark-custom">Gửi Admin duyệt (demo)</button>
                <a href="{{ route('recruiter.jobs') }}" class="btn btn-light">Hủy</a>
            </div>
        </form>
    </div>
    <section id="job-preview" hidden aria-labelledby="job-preview-title">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <h2 id="job-preview-title" class="h4">Xem trước — chưa đăng, chưa duyệt</h2>
            <button type="button" id="back-to-editor" class="btn-dark-custom">Quay lại chỉnh sửa</button>
        </div>
        <div id="job-preview-content"></div>
    </section>
@endsection
