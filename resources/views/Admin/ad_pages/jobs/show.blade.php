@extends('Admin.layout.admin')

@section('content')
    <div id="admin-detail">
        <section class="card p-4 mb-4">
            <h2 class="h4">{{ $selected?->title ?? 'Chưa có nội dung tin' }}</h2>
            <p>
                <a href="{{ route('admin.companies.show', ['id' => $job->company_id]) }}">{{ $job->company->name }}</a>
                · {{ $job->company->recruiter->name }} · {{ $job->company->recruiter->email }}
            </p>
            <p>Tin #{{ $job->id }} · Tạo: {{ App\Support\DisplayDate::format($job->created_at) }} · Cập nhật: {{ App\Support\DisplayDate::format($job->updated_at) }}</p>
            <div class="admin-actions mb-3">
                <span>Hoạt động: <span class="badge-table pending">{{ App\Support\JobInput::STATUSES[$job->status->value] }}</span></span>
                @if ($selected)
                    <span>Phiên bản V{{ $selected->version_number }}: <span class="badge-table pending">{{ App\Support\JobInput::REVIEWS[$selected->review_status->value] }}</span></span>
                @endif
            </div>
            <p>Bản được gán công khai: {{ $published ? 'V'.$published->version_number : 'Chưa có' }}. {{ $isPublic ? 'Tin đủ điều kiện hiển thị công khai.' : 'Tin hiện không đủ điều kiện hiển thị công khai.' }}</p>
            @if ($job->status_before_block)
                <p>Trước khi chặn: {{ App\Support\JobInput::STATUSES[$job->status_before_block->value] }}.</p>
            @endif
            <p class="small text-muted-green">Mở chặn giữ nguyên bản đã duyệt. Nếu trước là đang mở nhưng hết hạn hoặc không đủ điều kiện công khai, tin chuyển về tạm dừng. Tin đã đóng không tự mở lại.</p>
            <div class="admin-actions">
                @include('Admin.ad_pages.jobs.actions')
                @if ($selected?->review_status->value === 'pending')
                    <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.moderation.show', $selected->id) }}">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i> Xem & duyệt phiên bản
                    </a>
                @endif
                <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.jobs.history', ['id' => $job->id]) }}">
                    <i class="bi bi-clock-history" aria-hidden="true"></i> Lịch sử
                </a>
                <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.jobs.index') }}">Danh sách tin</a>
            </div>
        </section>
        <section class="card p-4">
            <h2 class="h5 mb-3">So sánh bản công khai / phiên bản đang xem</h2>
            <div class="table-responsive">
                <table class="table-custom admin-table admin-comparison">
                    <thead>
                        <tr>
                            <th>Nội dung</th>
                            <th>Bản công khai {{ $published ? 'V'.$published->version_number : '—' }}</th>
                            <th>Đang xem {{ $selected ? 'V'.$selected->version_number : '—' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($selectedContent as $label => $value)
                            <tr class="{{ $published && ($publishedContent[$label] ?? null) !== $value ? 'admin-changed' : '' }}">
                                <th scope="row">{{ $label }}</th>
                                <td class="admin-text">{{ $published ? ($publishedContent[$label] ?? 'Chưa nhập') : 'Chưa có bản công khai' }}</td>
                                <td class="admin-text">{{ $value === null || $value === '' ? 'Chưa nhập' : $value }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="admin-empty">Chưa có phiên bản.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        @include('Admin.ad_pages.jobs.histories')
    </div>
    @include('Admin.component.status-modal')
@endsection
