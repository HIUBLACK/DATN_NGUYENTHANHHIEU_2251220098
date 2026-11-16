@extends('Admin.layout.admin')

@section('content')
    <div class="row g-4 mb-4" id="admin-user-stats"></div>
    <section class="mb-4" aria-labelledby="system-stat-title">
        <h2 class="h5 mb-3" id="system-stat-title">Doanh nghiệp và tin tuyển dụng</h2>
        <div class="row g-3" id="admin-system-stats"></div>
    </section>
    <section class="card p-4 mb-4" aria-labelledby="trend-title">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5" id="trend-title">Hoạt động tuyển dụng theo thời gian</h2>
                <p class="text-muted-green small">Thẻ thống kê thể hiện toàn bộ dữ liệu; biểu đồ và tổng bên dưới theo khoảng ngày đã chọn.</p>
            </div>
            <form id="admin-date-filter" class="admin-actions align-items-end">
                <div>
                    <label for="admin-date-start" class="form-label-custom">Từ ngày</label>
                    <input type="date" id="admin-date-start" class="form-control-custom" required>
                </div>
                <div>
                    <label for="admin-date-end" class="form-label-custom">Đến ngày</label>
                    <input type="date" id="admin-date-end" class="form-control-custom" required>
                </div>
                <button class="btn-dark-custom" type="submit">Áp dụng</button>
            </form>
        </div>
        <p id="admin-range-summary" role="status"></p>
        <div id="admin-trend-chart" aria-label="Biểu đồ số tin mới và lượt ứng tuyển"></div>
        <details>
            <summary class="admin-summary">Xem số liệu biểu đồ dạng bảng</summary>
            <div class="table-responsive mt-3">
                <table class="table-custom admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Ngày</th>
                            <th scope="col">Tin mới</th>
                            <th scope="col">Lượt ứng tuyển</th>
                        </tr>
                    </thead>
                    <tbody id="admin-trend-table"></tbody>
                </table>
            </div>
        </details>
    </section>
    <section aria-labelledby="application-stat-title">
        <h2 class="h5 mb-3" id="application-stat-title">Hồ sơ ứng tuyển toàn hệ thống</h2>
        <div class="row g-3" id="admin-application-stats"></div>
    </section>
@endsection
