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
            <div class="col-md-2">
                <button type="reset" class="btn btn-light">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                    Xóa tìm kiếm
                </button>
            </div>
        </form>
    </div>
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
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="application-table-body"></tbody>
            </table>
        </div>
    </div>
@endsection
