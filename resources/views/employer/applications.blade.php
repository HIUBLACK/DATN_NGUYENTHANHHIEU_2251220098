@extends('employer.layout')

@section('content')
    <div class="card p-4 mb-4">
        <form id="application-filters" method="get" action="{{ route('recruiter.applications') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="candidate-search" class="form-label-custom">Tìm theo tên ứng viên</label>
                <input id="candidate-search" name="q" value="{{ request('q') }}" maxlength="150" class="form-control-custom" placeholder="Nhập họ tên">
            </div>
            <div class="col-md-3">
                <label for="application-status" class="form-label-custom">Trạng thái hồ sơ</label>
                <select id="application-status" name="status" class="form-select-custom">
                    <option value="">Tất cả trạng thái</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="application-job" class="form-label-custom">Tin tuyển dụng</label>
                <select id="application-job" name="jobId" class="form-select-custom">
                    <option value="">Tất cả tin tuyển dụng</option>
                    @foreach ($jobs as $job)
                        <option value="{{ $job->id }}" @selected((string) request('jobId') === (string) $job->id)>{{ $job->latestVersion?->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="application-ai-status" class="form-label-custom">Đánh giá AI</label>
                <select id="application-ai-status" class="form-select-custom" disabled>
                    <option>Chưa triển khai đánh giá AI</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="application-sort" class="form-label-custom">Sắp xếp</label>
                <select id="application-sort" class="form-select-custom" disabled>
                    <option>Ngày ứng tuyển mới nhất</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-dark">
                    <i class="bi bi-search" aria-hidden="true"></i> Tìm kiếm
                </button>
                <a href="{{ route('recruiter.applications') }}" class="btn btn-light">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                    Xóa tìm kiếm
                </a>
            </div>
        </form>
    </div>
    <p class="small text-muted-green">Chưa triển khai đánh giá AI. Quyết định tuyển dụng thuộc về nhà tuyển dụng.</p>
    <p id="application-count">{{ $applications->total() }} hồ sơ ứng tuyển</p>
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
                <tbody id="application-table-body">
                    @forelse ($applications as $application)
                        <tr>
                            <td class="recruiter-order">{{ $applications->firstItem() + $loop->index }}</td>
                            <td>
                                <a href="{{ route('recruiter.application-detail', ['id' => $application->public_id]) }}">{{ $application->candidate->name }}</a>
                                <p class="small mb-0">{{ $application->jobVersion->title }}</p>
                            </td>
                            <td>
                                {{ $application->applied_at->format('d/m/Y H:i') }}
                                <p class="small mb-0">Lượt {{ $application->attempt_no }}/2</p>
                            </td>
                            <td>
                                <span class="badge-table {{ $statusTones[$application->status->value] }}">{{ $statusLabels[$application->status->value] }}</span>
                            </td>
                            <td>
                                <span class="text-muted-green">Chưa đánh giá</span>
                            </td>
                            <td>
                                <div class="recruiter-actions">
                                    <a class="btn btn-light btn-sm" href="{{ route('recruiter.application-detail', ['id' => $application->public_id]) }}">
                                        <i class="bi bi-person-lines-fill" aria-hidden="true"></i> Chi tiết
                                    </a>
                                    <a class="btn btn-outline-dark btn-sm" href="{{ route('recruiter.application-pdf', $application->public_id) }}" target="_blank" rel="noopener">
                                        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Xem CV
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center p-4">Không có hồ sơ phù hợp.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">
        {{ $applications->links('pagination::bootstrap-5') }}
    </div>
@endsection
