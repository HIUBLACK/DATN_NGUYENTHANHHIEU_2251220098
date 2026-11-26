@extends('Admin.layout.admin')

@section('content')
    <div class="card p-4 mb-4">
        <form method="get" id="admin-filters" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="admin-search" class="form-label-custom">Tìm kiếm</label>
                <input id="admin-search" name="q" value="{{ request('q') }}" class="form-control-custom" maxlength="150" placeholder="Tiêu đề hoặc doanh nghiệp">
            </div>
            <div class="col-md-4">
                <label for="filter-status" class="form-label-custom">Hoạt động</label>
                <select id="filter-status" name="status" class="form-select-custom">
                    <option value="">Tất cả</option>
                    @foreach (App\Support\JobInput::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="filter-review" class="form-label-custom">Kiểm duyệt phiên bản mới nhất</label>
                <select id="filter-review" name="review" class="form-select-custom">
                    <option value="">Tất cả</option>
                    @foreach (App\Support\JobInput::REVIEWS as $value => $label)
                        <option value="{{ $value }}" @selected(request('review') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 admin-actions">
                <button type="submit" class="btn-dark-custom">
                    <i class="bi bi-search" aria-hidden="true"></i> Tìm kiếm
                </button>
                <a href="{{ route('admin.jobs.index') }}" class="btn btn-outline-dark">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Xóa bộ lọc
                </a>
            </div>
        </form>
    </div>
    <p id="admin-result-count" role="status">{{ $records->total() }} tin tuyển dụng</p>
    <div class="table-card-custom">
        <div class="table-responsive">
            <table class="table-custom admin-table">
                <thead>
                    <tr>
                        <th>ID / Tiêu đề</th>
                        <th>Doanh nghiệp / Recruiter</th>
                        <th>Ngày tạo / Cập nhật / Hạn</th>
                        <th>Hoạt động</th>
                        <th>Kiểm duyệt / Bản công khai</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $job)
                        <tr>
                            <td>
                                <small>TIN-{{ $job->id }}</small>
                                <div>
                                    <strong>{{ $job->latestVersion?->title ?? 'Chưa có nội dung' }}</strong>
                                </div>
                            </td>
                            <td>
                                {{ $job->company->name }}
                                <div>{{ $job->company->recruiter->name }}</div>
                            </td>
                            <td>
                                {{ App\Support\DisplayDate::format($job->created_at) }}
                                <div>Cập nhật: {{ App\Support\DisplayDate::format($job->updated_at) }}</div>
                                <div>Hạn bản đã duyệt: {{ $job->publishedVersion?->application_deadline?->format('d/m/Y') ?? 'Chưa có' }}</div>
                            </td>
                            <td>
                                <span class="badge-table {{ $job->status->value === 'open' ? 'success' : ($job->status->value === 'blocked' ? 'failed' : 'pending') }}">{{ App\Support\JobInput::STATUSES[$job->status->value] }}</span>
                            </td>
                            <td>
                                @if ($job->latestVersion)
                                    <span class="badge-table {{ $job->latestVersion->review_status->value === 'approved' ? 'success' : 'pending' }}">V{{ $job->latestVersion->version_number }} · {{ App\Support\JobInput::REVIEWS[$job->latestVersion->review_status->value] }}</span>
                                @endif
                                <div>Bản công khai: {{ $job->publishedVersion ? 'V'.$job->publishedVersion->version_number : 'Chưa có' }}</div>
                            </td>
                            <td>
                                <div class="admin-actions">
                                    <a href="{{ route('admin.jobs.show', ['id' => $job->id]) }}" class="btn btn-outline-dark btn-sm">
                                        <i class="bi bi-eye" aria-hidden="true"></i> Chi tiết
                                    </a>
                                    @include('Admin.ad_pages.jobs.actions')
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="admin-empty">Chưa có tin tuyển dụng phù hợp.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $records->links('pagination::bootstrap-5') }}</div>
    @include('Admin.component.status-modal')
@endsection
