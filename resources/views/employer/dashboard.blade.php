@extends('employer.layout')

@section('content')
    <section aria-labelledby="job-statistics">
        <h2 class="h5 mb-3" id="job-statistics">Tin tuyển dụng</h2>
        <div class="row g-4 mb-4" id="job-stat-cards"></div>
    </section>
    <div class="row g-4 mb-4">
        <section class="col-lg-5" aria-labelledby="job-chart-title">
            <div class="card p-4 h-100">
                <h2 class="h5" id="job-chart-title">
                    <i class="bi bi-pie-chart me-2" aria-hidden="true"></i>
                    Phân bố tin tuyển dụng
                </h2>
                <p class="small text-muted-green">Trạng thái hiện tại của toàn bộ tin trong phiên demo.</p>
                <div id="recruiter-job-chart"></div>
            </div>
        </section>
        <section class="col-lg-7" aria-labelledby="application-chart-title">
            <div class="card p-4 h-100">
                <h2 class="h5" id="application-chart-title">
                    <i class="bi bi-bar-chart-line me-2" aria-hidden="true"></i>
                    Phân bố hồ sơ ứng tuyển
                </h2>
                <p class="small text-muted-green">Số hồ sơ ở từng trạng thái hiện tại, không phải tỷ lệ chuyển đổi giữa các bước.</p>
                <div id="recruiter-application-chart"></div>
            </div>
        </section>
    </div>
    <section class="card p-4 mb-4" aria-labelledby="attention-title">
        <h2 class="h5 mb-3" id="attention-title">
            <i class="bi bi-list-check me-2" aria-hidden="true"></i>
            Cần theo dõi
        </h2>
        <div class="row g-3" id="recruiter-attention"></div>
    </section>
    <section aria-labelledby="application-statistics">
        <h2 class="h5 mb-3" id="application-statistics">Hồ sơ ứng tuyển</h2>
        <div class="row g-4 mb-4" id="application-stat-cards"></div>
    </section>
    <div class="card p-4">
        <h2 class="card-title">Bắt đầu công việc tuyển dụng</h2>
        <p class="text-muted-green">Tạo tin, theo dõi kiểm duyệt và xử lý hồ sơ ứng viên trong cùng không gian.</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('recruiter.job-create') }}" class="btn-dark-custom">Tạo tin tuyển dụng</a>
            <a href="{{ route('recruiter.applications') }}" class="btn btn-light">Xem hồ sơ ứng tuyển</a>
        </div>
    </div>
@endsection
