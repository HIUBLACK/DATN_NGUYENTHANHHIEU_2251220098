<div id="admin-detail">
    <section class="card p-4 mb-4">
        <h2 class="h4">{{ $section === 'applications' ? $record->candidate->name : $record->name }}</h2>
        <p>
            <span class="badge-table {{ $record->status->value === 'active' ? 'success' : 'pending' }}">{{ $statusLabels[$record->status->value] }}</span>
        </p>
        <dl class="row">
            <dt class="col-sm-3">Mã</dt>
            <dd class="col-sm-9">{{ $record->id }}</dd>
            @if ($section === 'users')
                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9">{{ $record->email }}</dd>
                <dt class="col-sm-3">Vai trò</dt>
                <dd class="col-sm-9">{{ $record->role->value === 'candidate' ? 'Ứng viên' : 'Nhà tuyển dụng' }}</dd>
                <dt class="col-sm-3">Ngày đăng ký</dt>
                <dd class="col-sm-9">{{ \App\Support\DisplayDate::format($record->created_at) }}</dd>
                @if ($record->candidateProfile)
                    @foreach (['phone' => 'Điện thoại', 'location' => 'Địa chỉ', 'professional_title' => 'Nghề nghiệp', 'experience_summary' => 'Kinh nghiệm', 'summary' => 'Giới thiệu'] as $field => $label)
                        <dt class="col-sm-3">{{ $label }}</dt>
                        <dd class="col-sm-9 admin-text">{{ $record->candidateProfile->$field ?: 'Chưa cập nhật' }}</dd>
                    @endforeach
                @endif
                @if ($record->company)
                    <dt class="col-sm-3">Doanh nghiệp</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route('admin.companies.show', ['id' => $record->company->id]) }}">{{ $record->company->name }}</a>
                    </dd>
                @endif
            @elseif ($section === 'companies')
                <dt class="col-sm-3">Nhà tuyển dụng</dt>
                <dd class="col-sm-9">{{ $record->recruiter->name }} · {{ $record->recruiter->email }}</dd>
                <dt class="col-sm-3">Liên hệ doanh nghiệp</dt>
                <dd class="col-sm-9">{{ $record->email }} · {{ $record->phone }}</dd>
                <dt class="col-sm-3">Số tin</dt>
                <dd class="col-sm-9">{{ $record->jobs_count }}</dd>
            @else
                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9">{{ $record->candidate->email }}</dd>
                <dt class="col-sm-3">Tin ứng tuyển</dt>
                <dd class="col-sm-9">{{ $record->jobVersion->title }} · Phiên bản {{ $record->jobVersion->version_number }}</dd>
                <dt class="col-sm-3">Doanh nghiệp</dt>
                <dd class="col-sm-9">{{ $record->job->company->name }} · {{ $record->job->company->recruiter->name }}</dd>
                <dt class="col-sm-3">Ngày nộp / Lượt</dt>
                <dd class="col-sm-9">{{ \App\Support\DisplayDate::format($record->applied_at) }} · Lượt {{ $record->attempt_no }}</dd>
            @endif
        </dl>
        <div class="admin-actions">
            <a class="btn btn-outline-dark" href="{{ route('admin.'.$section.'.index') }}">Quay lại danh sách</a>
            @if ($section !== 'applications')
                @include('Admin.component.status-button')
            @endif
        </div>
        @if ($section === 'applications')
            <p class="alert alert-light border mt-3 mb-0">Chế độ giám sát: không chuyển trạng thái, không xem ghi chú nội bộ hoặc nội dung CV theo quyền hiện tại.</p>
        @endif
    </section>
    @if ($section === 'companies')
        @include('shared.company-profile')
    @endif
    <section class="card p-4 mt-4">
        <h2 class="h5">{{ $section === 'applications' ? 'Lịch sử xử lý hồ sơ' : 'Lịch sử khóa/mở khóa' }}</h2>
        <div class="table-responsive">
            <table class="table-custom admin-table">
                <thead>
                    <tr>
                        <th>Hành động / Kết quả</th>
                        <th>Người thực hiện</th>
                        <th>Thời gian</th>
                        <th>Lý do</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $event)
                        <tr>
                            <td>{{ $section === 'applications' ? $statusLabels[$event->new_status->value] : ($event->new_values['status'] === 'locked' ? 'Khóa' : 'Mở khóa') }}</td>
                            <td>{{ $event->actor?->name ?? 'Hệ thống' }}</td>
                            <td>{{ \App\Support\DisplayDate::format($event->created_at) }}</td>
                            <td class="admin-text">{{ $event->reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="admin-empty">Chưa có lịch sử thao tác.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($section !== 'applications')
            {{ $history->links('pagination::bootstrap-5') }}
        @endif
    </section>
</div>
@if ($section !== 'applications')
    @include('Admin.component.status-modal')
@endif
