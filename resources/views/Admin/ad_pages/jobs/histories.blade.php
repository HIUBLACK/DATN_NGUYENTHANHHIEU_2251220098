<section class="card p-4 mt-4">
    <h2 class="h5">Các phiên bản tin</h2>
    <div class="table-responsive">
        <table class="table-custom admin-table">
            <thead>
                <tr>
                    <th>Phiên bản</th>
                    <th>Tiêu đề</th>
                    <th>Kiểm duyệt</th>
                    <th>Ngày tạo / Gửi duyệt</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($versions as $version)
                    <tr>
                        <td>V{{ $version->version_number }} {{ $job->published_version_id === $version->id ? '· Bản công khai' : '' }}</td>
                        <td>{{ $version->title }}</td>
                        <td>
                            <span class="badge-table pending">{{ App\Support\JobInput::REVIEWS[$version->review_status->value] }}</span>
                        </td>
                        <td>
                            {{ App\Support\DisplayDate::format($version->created_at) }}
                            <div>Gửi duyệt: {{ App\Support\DisplayDate::format($version->submitted_at) ?: 'Chưa gửi' }}</div>
                        </td>
                        <td>
                            <a href="{{ route('admin.jobs.show', ['id' => $job->id, 'version' => $version->id]) }}" class="btn btn-outline-dark btn-sm">
                                <i class="bi bi-eye" aria-hidden="true"></i> Xem phiên bản
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="admin-empty">Chưa có phiên bản.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $versions->links('pagination::bootstrap-5') }}
</section>
<section class="card p-4 mt-4">
    <h2 class="h5">Lịch sử kiểm duyệt</h2>
    <div class="table-responsive">
        <table class="table-custom admin-table">
            <thead>
                <tr>
                    <th>Phiên bản</th>
                    <th>Kết quả</th>
                    <th>Admin</th>
                    <th>Thời gian</th>
                    <th>Lý do</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($moderation as $event)
                    <tr>
                        <td>V{{ $event->jobVersion->version_number }}</td>
                        <td>{{ $event->action === 'approve' ? 'Phê duyệt' : 'Từ chối' }}</td>
                        <td>{{ $event->admin->name }}</td>
                        <td>{{ App\Support\DisplayDate::format($event->created_at) }}</td>
                        <td class="admin-text">{{ $event->reason ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="admin-empty">Chưa có lịch sử kiểm duyệt.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $moderation->links('pagination::bootstrap-5') }}
</section>
<section class="card p-4 mt-4">
    <h2 class="h5">Lịch sử vận hành và chặn tin</h2>
    <div class="table-responsive">
        <table class="table-custom admin-table">
            <thead>
                <tr>
                    <th>Hành động</th>
                    <th>Trước / Sau</th>
                    <th>Người thực hiện</th>
                    <th>Thời gian</th>
                    <th>Lý do</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($audit as $event)
                    <tr>
                        <td>{{ ['job.create' => 'Tạo tin', 'job.open' => 'Mở tin', 'job.pause' => 'Tạm dừng', 'job.close' => 'Đóng tin', 'job.block' => 'Chặn tin', 'job.unblock' => 'Mở chặn'][$event->action] ?? $event->action }}</td>
                        <td>{{ App\Support\JobInput::STATUSES[$event->old_values['status'] ?? ''] ?? '—' }} → {{ App\Support\JobInput::STATUSES[$event->new_values['status']] }}</td>
                        <td>{{ $event->actor->name }}</td>
                        <td>{{ App\Support\DisplayDate::format($event->created_at) }}</td>
                        <td class="admin-text">{{ $event->reason ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="admin-empty">Chưa có lịch sử vận hành.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $audit->links('pagination::bootstrap-5') }}
</section>
