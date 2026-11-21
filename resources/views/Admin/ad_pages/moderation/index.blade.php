@extends('Admin.layout.admin')

@section('content')
    <div class="card p-4 mb-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="moderation-search" class="form-label-custom">Tên tin hoặc doanh nghiệp</label>
                <input id="moderation-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="150" class="form-control-custom">
            </div>
            <div class="col-md-4">
                <label for="moderation-status" class="form-label-custom">Trạng thái tin</label>
                <select id="moderation-status" name="status" class="form-control-custom">
                    <option value="">Tất cả</option>
                    @foreach (App\Support\JobInput::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    Tìm kiếm
                </button>
            </div>
        </form>
    </div>
    <p>{{ $versions->total() }} phiên bản đang chờ duyệt</p>
    <div class="table-card-custom table-responsive">
        <table class="table-custom admin-table">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tin / Doanh nghiệp</th>
                    <th>Phiên bản</th>
                    <th>Ngày gửi</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($versions as $version)
                    <tr>
                        <td>{{ $versions->firstItem() + $loop->index }}</td>
                        <td>
                            <strong>{{ $version->title }}</strong>
                            <small class="d-block">{{ $version->job->company->name }}</small>
                        </td>
                        <td>V{{ $version->version_number }}</td>
                        <td>{{ \App\Support\DisplayDate::format($version->submitted_at) }}</td>
                        <td>
                            <span class="badge bg-warning text-dark">Chờ duyệt</span>
                            <small class="d-block">{{ App\Support\JobInput::STATUSES[$version->job->status->value] }}</small>
                        </td>
                        <td>
                            <a href="{{ route('admin.moderation.show', $version->id) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                                Xem và kiểm duyệt
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Không có phiên bản chờ duyệt phù hợp.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $versions->links() }}
    </div>
@endsection
