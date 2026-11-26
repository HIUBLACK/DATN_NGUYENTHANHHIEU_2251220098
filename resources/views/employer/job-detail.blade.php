@extends('employer.layout')

@section('content')
    <div class="card p-4 mb-4">
        <h2 class="h4">{{ $version->title }}</h2>
        <p>{{ App\Support\JobInput::STATUSES[$job->status->value] }} · Phiên bản {{ $version->version_number }} · {{ App\Support\JobInput::REVIEWS[$version->review_status->value] }}</p>
        <p>Bản công khai: {{ $job->publishedVersion?->version_number ?? 'Chưa có' }}. Đây là bản xem trước riêng của bạn.</p>
        <div class="d-flex flex-wrap gap-2">
            @can('update', $job)
                <a href="{{ route('recruiter.job-edit', $job->id) }}" class="btn btn-outline-primary">Chỉnh sửa</a>
                @if ($version->review_status->value === 'draft')
                    <form method="post" action="{{ route('recruiter.job-submit', $job->id) }}">
                        @csrf
                        <input type="hidden" name="version_id" value="{{ $version->id }}">
                        <input type="hidden" name="updated_at" value="{{ $version->getRawOriginal('updated_at') }}">
                        <button class="btn-dark-custom" type="submit">
                            <i class="bi bi-send" aria-hidden="true"></i>
                            Gửi bản đã lưu để duyệt
                        </button>
                    </form>
                @endif
            @endcan
            @can('operate', $job)
                @foreach (['pause' => ['Tạm dừng', 'pause-circle', 'open'], 'reopen' => ['Mở lại', 'play-circle', 'paused'], 'close' => ['Đóng tin', 'x-circle', 'open,paused']] as $action => [$label, $icon, $states])
                    @if (in_array($job->status->value, explode(',', $states), true))
                        <form method="post" action="{{ route('recruiter.job-transition', [$job->id, $action]) }}">
                            @csrf
                            <button class="btn btn-outline-dark" type="submit">
                                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                                {{ $label }}
                            </button>
                        </form>
                    @endif
                @endforeach
            @endcan
        </div>
    </div>
    <div class="card p-4 mb-4">
        <p>Tỉnh/thành phố: {{ App\Support\Provinces::ALL[$version->province_code] ?? 'Chưa cập nhật' }}</p>
        @foreach (['position' => 'Vị trí', 'description' => 'Mô tả công việc', 'requirements' => 'Yêu cầu ứng viên', 'benefits' => 'Quyền lợi', 'full_location' => 'Địa điểm chi tiết'] as $field => $label)
            <h3 class="h5">{{ $label }}</h3>
            <p class="company-profile-text">{{ $version->$field ?? 'Chưa nhập' }}</p>
        @endforeach
        <p>Lương (VND/tháng): {{ $version->salary_min ?? 'Chưa nhập' }} – {{ $version->salary_max ?? 'Chưa nhập' }}</p>
        <p>Hạn ứng tuyển: {{ $version->application_deadline?->format('d/m/Y') ?? 'Chưa nhập' }}</p>
        <p>Lĩnh vực: {{ $version->industry?->name ?? 'Chưa chọn' }} · Chuyên môn: {{ $version->specialization?->name ?? 'Chưa chọn' }} · Cấp bậc: {{ $version->level?->name ?? 'Chưa chọn' }}</p>
        <p>Kỹ năng: {{ $version->skills->pluck('name')->implode(', ') ?: 'Chưa chọn' }}</p>
        <p>Hình thức: {{ App\Support\JobInput::MODES[$version->work_mode] ?? 'Chưa chọn' }} · Loại: {{ App\Support\JobInput::TYPES[$version->employment_type] ?? 'Chưa chọn' }} · Số lượng: {{ $version->quantity ?? 'Chưa nhập' }}</p>
    </div>
    <div class="card p-4">
        <h2 class="h5">Lịch sử phiên bản</h2>
        @foreach ($versions as $history)
            <p>Phiên bản {{ $history->version_number }} · {{ App\Support\JobInput::REVIEWS[$history->review_status->value] }}</p>
            @if ($history->moderationLog?->reason)
                <div class="alert alert-warning">Lý do từ chối: {{ $history->moderationLog->reason }}</div>
            @endif
        @endforeach
        <p class="mb-0 text-muted">Quản lý hồ sơ ứng tuyển và đánh giá AI cho tin thật sẽ được triển khai ở giai đoạn sau.</p>
    </div>
@endsection
