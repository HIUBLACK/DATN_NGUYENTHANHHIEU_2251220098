<div class="card p-4 mb-4">
    <form method="get" id="admin-filters" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="admin-search" class="form-label-custom">Tìm kiếm</label>
            <input id="admin-search" name="q" class="form-control-custom" value="{{ request('q') }}" maxlength="150" placeholder="Nhập tên hoặc từ khóa">
        </div>
        <div class="col-md-4">
            <label for="filter-status" class="form-label-custom">Trạng thái</label>
            <select id="filter-status" name="status" class="form-select-custom">
                <option value="">Tất cả</option>
                @foreach ($statusLabels as $value => $label)
                    @if (($section === 'applications') !== in_array($value, ['active', 'locked']))
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        @if ($section === 'users')
            <div class="col-md-4">
                <label for="filter-role" class="form-label-custom">Vai trò</label>
                <select id="filter-role" name="role" class="form-select-custom">
                    <option value="">Tất cả</option>
                    <option value="candidate" @selected(request('role') === 'candidate')>Ứng viên</option>
                    <option value="recruiter" @selected(request('role') === 'recruiter')>Nhà tuyển dụng</option>
                </select>
            </div>
        @endif
        @if ($section === 'applications')
            @foreach (['candidate' => 'Ứng viên', 'recruiter' => 'Nhà tuyển dụng', 'company' => 'Doanh nghiệp', 'job' => 'Tin tuyển dụng'] as $field => $label)
                <div class="col-md-4">
                    <label for="filter-{{ $field }}" class="form-label-custom">{{ $label }}</label>
                    <input id="filter-{{ $field }}" name="{{ $field }}" value="{{ request($field) }}" class="form-control-custom" maxlength="150">
                </div>
            @endforeach
        @endif
        <div class="col-12 admin-actions">
            <button class="btn-dark-custom" type="submit">
                <i class="bi bi-search" aria-hidden="true"></i> Tìm kiếm
            </button>
            <a href="{{ route('admin.'.$section.'.index') }}" class="btn btn-outline-dark">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Xóa bộ lọc
            </a>
        </div>
    </form>
</div>
<p id="admin-result-count" role="status">{{ $records->total() }} kết quả</p>
<div class="table-card-custom">
    <div class="table-responsive">
        <table class="table-custom admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    @if ($section === 'users')
                        <th>Họ tên / Email</th>
                        <th>Vai trò</th>
                        <th>Ngày đăng ký</th>
                    @elseif ($section === 'companies')
                        <th>Doanh nghiệp</th>
                        <th>Recruiter / Email</th>
                        <th>Số tin</th>
                    @else
                        <th>Ứng viên</th>
                        <th>Doanh nghiệp / Recruiter</th>
                        <th>Tin tuyển dụng</th>
                        <th>Ngày nộp / Lượt</th>
                    @endif
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td>{{ $record->id }}</td>
                        @if ($section === 'users')
                            <td>
                                <strong>{{ $record->name }}</strong>
                                <div>{{ $record->email }}</div>
                            </td>
                            <td>{{ $record->role->value === 'candidate' ? 'Ứng viên' : 'Nhà tuyển dụng' }}</td>
                            <td>{{ \App\Support\DisplayDate::format($record->created_at) }}</td>
                        @elseif ($section === 'companies')
                            <td>
                                <i class="bi bi-building admin-company-logo" aria-hidden="true"></i>
                                <strong>{{ $record->name }}</strong>
                            </td>
                            <td>
                                {{ $record->recruiter->name }}
                                <div>{{ $record->recruiter->email }}</div>
                            </td>
                            <td>{{ $record->jobs_count }}</td>
                        @else
                            <td>{{ $record->candidate->name }}</td>
                            <td>
                                {{ $record->job->company->name }}
                                <div>{{ $record->job->company->recruiter->name }}</div>
                            </td>
                            <td>{{ $record->jobVersion->title }}</td>
                            <td>{{ \App\Support\DisplayDate::format($record->applied_at) }} · Lượt {{ $record->attempt_no }}</td>
                        @endif
                        <td>
                            <span class="badge-table {{ in_array($record->status->value, ['active', 'hired']) ? 'success' : 'pending' }}">{{ $statusLabels[$record->status->value] }}</span>
                        </td>
                        <td>
                            <div class="admin-actions">
                                <a href="{{ route('admin.'.$section.'.show', ['id' => $section === 'applications' ? $record->public_id : $record->id]) }}" class="btn btn-outline-dark btn-sm">
                                    <i class="bi bi-eye" aria-hidden="true"></i> Xem
                                </a>
                                @if ($section !== 'applications')
                                    @include('Admin.component.status-button')
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="admin-empty">Chưa có dữ liệu phù hợp.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $records->links('pagination::bootstrap-5') }}</div>
@if ($section !== 'applications')
    @include('Admin.component.status-modal')
@endif
