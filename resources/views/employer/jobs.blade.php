@extends('employer.layout')

@section('content')
    <div class="card p-4 mb-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="job-search" class="form-label-custom">Tìm tiêu đề</label>
                <input id="job-search" name="q" value="{{ request('q') }}" class="form-control-custom" maxlength="150">
            </div>
            @foreach (['activity' => ['Hoạt động', App\Support\JobInput::STATUSES], 'moderation' => ['Kiểm duyệt', App\Support\JobInput::REVIEWS]] as $field => [$label, $options])
                <div class="col-md-3">
                    <label for="job-{{ $field }}" class="form-label-custom">{{ $label }}</label>
                    <select id="job-{{ $field }}" name="{{ $field }}" class="form-select-custom">
                        <option value="">Tất cả</option>
                        @foreach ($options as $value => $text)
                            <option value="{{ $value }}" @selected(request($field) === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-md-1">
                <button class="btn btn-outline-dark" aria-label="Tìm tin" type="submit">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </button>
            </div>
        </form>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="mb-0">{{ $jobs->total() }} tin tuyển dụng</p>
        @if ($canCreateJob)
            <a href="{{ route('recruiter.job-create') }}" class="btn-dark-custom">
                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                Tạo tin mới
            </a>
        @else
            <p class="mb-0">Cần hồ sơ doanh nghiệp đang hoạt động để tạo tin.</p>
        @endif
    </div>
    <div class="table-card-custom table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tiêu đề / Ngày tạo</th>
                    <th>Hạn ứng tuyển</th>
                    <th>Hoạt động</th>
                    <th>Kiểm duyệt</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($jobs as $job)
                    <tr>
                        <td>{{ $jobs->firstItem() + $loop->index }}</td>
                        <td>
                            <strong>{{ $job->latestVersion?->title }}</strong>
                            <small class="d-block">{{ $job->created_at->format('d/m/Y') }}</small>
                        </td>
                        <td>{{ $job->publishedVersion?->application_deadline?->format('d/m/Y') ?? $job->latestVersion?->application_deadline?->format('d/m/Y') ?? 'Chưa nhập' }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ App\Support\JobInput::STATUSES[$job->status->value] }}</span>
                        </td>
                        <td>{{ App\Support\JobInput::REVIEWS[$job->latestVersion->review_status->value] }}</td>
                        <td>
                            <a href="{{ route('recruiter.job-detail', $job->id) }}" class="btn btn-outline-dark btn-sm">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                                Xem
                            </a>
                            @can('update', $job)
                                <a href="{{ route('recruiter.job-edit', $job->id) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                    Sửa
                                </a>
                            @endcan
                            @can('operate', $job)
                                @if ($job->status->value === 'open')
                                    <form method="post" action="{{ route('recruiter.job-transition', [$job->id, 'pause']) }}" class="d-inline-block my-1">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-warning btn-sm">
                                            <i class="bi bi-pause-circle" aria-hidden="true"></i>
                                            Tạm dừng
                                        </button>
                                    </form>
                                @elseif ($job->status->value === 'paused')
                                    <form method="post" action="{{ route('recruiter.job-transition', [$job->id, 'reopen']) }}" class="d-inline-block my-1">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-play-circle" aria-hidden="true"></i>
                                            Mở lại
                                        </button>
                                    </form>
                                @endif
                                @if (in_array($job->status->value, ['open', 'paused'], true))
                                    <form method="post" action="{{ route('recruiter.job-transition', [$job->id, 'close']) }}" class="d-inline-block my-1">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-x-circle" aria-hidden="true"></i>
                                            Đóng tin
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Chưa có tin tuyển dụng phù hợp.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $jobs->links() }}
    </div>
@endsection
