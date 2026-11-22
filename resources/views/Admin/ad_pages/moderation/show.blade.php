@extends('Admin.layout.admin')

@section('content')
    <div class="card p-4 mb-4">
        <h2 class="h4">{{ $version->job->company->name }}</h2>
        <p>{{ $version->job->company->address }}</p>
        <p>Nhà tuyển dụng: {{ $version->job->company->recruiter->name }}</p>
        <p>Doanh nghiệp: {{ $version->job->company->status->value === 'active' ? 'Hoạt động' : 'Bị khóa' }} · Tài khoản: {{ $version->job->company->recruiter->status->value === 'active' ? 'Hoạt động' : 'Bị khóa' }}</p>
        <p>Tin: {{ App\Support\JobInput::STATUSES[$version->job->status->value] }} · Phiên bản: {{ App\Support\JobInput::REVIEWS[$version->review_status->value] }}</p>
        <p>Ngày gửi: {{ \App\Support\DisplayDate::format($version->submitted_at) }}</p>
    </div>
    <div class="row g-4">
        <div class="{{ $published && $published->id !== $version->id ? 'col-lg-6' : 'col-12' }}">
            <div class="card p-4 h-100">
                <h2 class="h4 mb-3">Nội dung phiên bản V{{ $version->version_number }}</h2>
                @include('Admin.ad_pages.moderation.content', ['content' => $version])
            </div>
        </div>
        @if ($published && $published->id !== $version->id)
            <div class="col-lg-6">
                <div class="card p-4 h-100">
                    <h2 class="h4 mb-3">Đối chiếu bản đã duyệt V{{ $published->version_number }}</h2>
                    @include('Admin.ad_pages.moderation.content', ['content' => $published])
                </div>
            </div>
        @endif
    </div>
    <div class="card p-4 mt-4">
        @if ($version->review_status->value === 'pending')
            <p>Duyệt sẽ thay bản công khai bằng phiên bản này. Từ chối giữ nguyên bản công khai cũ. Tin tạm dừng/đã đóng không tự mở lại.</p>
            <form method="post" action="{{ route('admin.moderation.decide', [$version->id, 'approve']) }}" class="mb-4">
                @csrf
                <input type="hidden" name="updated_at" value="{{ $version->getRawOriginal('updated_at') }}">
                <input type="hidden" name="job_updated_at" value="{{ $version->job->getRawOriginal('updated_at') }}">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    Phê duyệt phiên bản
                </button>
            </form>
            <form method="post" action="{{ route('admin.moderation.decide', [$version->id, 'reject']) }}">
                @csrf
                <input type="hidden" name="updated_at" value="{{ $version->getRawOriginal('updated_at') }}">
                <input type="hidden" name="job_updated_at" value="{{ $version->job->getRawOriginal('updated_at') }}">
                <label for="moderation-reason" class="form-label-custom">Lý do từ chối *</label>
                <textarea id="moderation-reason" name="reason" class="form-control-custom mb-3" rows="3" required maxlength="2000">{{ old('reason') }}</textarea>
                <button class="btn btn-outline-danger" type="submit">
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                    Từ chối phiên bản
                </button>
            </form>
        @elseif ($version->moderationLog)
            <p>Đã xử lý bởi {{ $version->moderationLog->admin->name }} lúc {{ \App\Support\DisplayDate::format($version->reviewed_at) }}.</p>
            <p class="company-profile-text">{{ $version->moderationLog->reason }}</p>
        @endif
        <a href="{{ route('admin.jobs.queue') }}" class="mt-3">Quay lại danh sách chờ duyệt</a>
    </div>
@endsection
