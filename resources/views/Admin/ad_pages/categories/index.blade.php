@extends('Admin.layout.admin')

@section('content')
    @include('Admin.component.category-feedback')
    <div class="alert alert-light border">
        Danh mục ẩn vẫn được giữ trong dữ liệu lịch sử. Không thể chọn mới hoặc gửi duyệt tin với danh mục đang ẩn.
    </div>
    <div class="card p-4 mb-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="category-search" class="form-label-custom">Tìm theo tên</label>
                <input id="category-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" class="form-control-custom">
            </div>
            <div class="col-md-2">
                <label for="category-status" class="form-label-custom">Trạng thái</label>
                <select id="category-status" name="status" class="form-control-custom">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Đang hiện</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Đang ẩn</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="category-sort" class="form-label-custom">Sắp xếp</label>
                <select id="category-sort" name="sort" class="form-control-custom">
                    <option value="order" @selected(($filters['sort'] ?? 'order') === 'order')>Thứ tự hiển thị</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Tên A–Z</option>
                    <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Mới nhất</option>
                </select>
            </div>
            @if ($categoryType === 'specialties')
                <div class="col-md-3">
                    <label for="category-parent" class="form-label-custom">Nhóm nghề</label>
                    <select id="category-parent" name="parent" class="form-control-custom">
                        <option value="">Tất cả nhóm nghề</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(($filters['parent'] ?? '') == $parent->id)>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    Tìm kiếm
                </button>
                <a href="{{ route('admin.'.$categoryType.'.index') }}" class="btn btn-outline-dark">Xóa bộ lọc</a>
            </div>
        </form>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="mb-0">{{ $records->total() }} danh mục</p>
        <a href="{{ route('admin.'.$categoryType.'.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i>
            Thêm danh mục
        </a>
    </div>
    <div class="table-card-custom table-responsive">
        <table class="table-custom admin-table">
            <thead>
                <tr>
                    <th>STT / Mã</th>
                    <th>Tên / Mô tả</th>
                    <th>Thông tin liên quan</th>
                    <th>Thứ tự</th>
                    <th>Trạng thái</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td>
                            {{ $records->firstItem() + $loop->index }}
                            <small class="d-block">{{ $record->code }}</small>
                        </td>
                        <td>
                            <strong>{{ $record->name }}</strong>
                            <p class="small mb-0">{{ Illuminate\Support\Str::limit($record->description ?? '', 100) }}</p>
                        </td>
                        <td>
                            @if ($categoryType === 'occupations')
                                <a href="{{ route('admin.specialties.index', ['parent' => $record->id]) }}">{{ $record->references_count }} chuyên môn</a>
                            @else
                                <span>{{ $record->references_count }} phiên bản tin</span>
                            @endif
                            @if ($categoryType === 'specialties')
                                <small class="d-block">{{ $record->category->name }}</small>
                            @elseif ($categoryType === 'industries')
                                <small class="d-block">{{ $record->companies_count }} doanh nghiệp</small>
                            @elseif ($categoryType === 'skills')
                                <small class="d-block">{{ $record->resume_skills_count }} tham chiếu kỹ năng CV</small>
                            @endif
                        </td>
                        <td>{{ $record->sort_order }}</td>
                        <td>
                            <span class="badge {{ $record->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $record->is_active ? 'Đang hiện' : 'Đang ẩn' }}</span>
                        </td>
                        <td>{{ $record->created_at->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('admin.'.$categoryType.'.edit', $record->id) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                    Sửa
                                </a>
                                <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#category-status-modal"
                                    data-action="{{ route('admin.'.$categoryType.'.status', $record->id) }}"
                                    data-version="{{ $record->getRawOriginal('updated_at') }}" data-active="{{ $record->is_active ? '0' : '1' }}" data-name="{{ $record->name }}">
                                    <i class="bi {{ $record->is_active ? 'bi-eye-slash' : 'bi-eye' }}" aria-hidden="true"></i>
                                    {{ $record->is_active ? 'Ẩn' : 'Hiện' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">Chưa có danh mục phù hợp. Hãy thêm dữ liệu thật để sử dụng.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $records->links() }}
    </div>
    <div class="modal fade" id="category-status-modal" tabindex="-1" aria-labelledby="category-status-title" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" class="modal-content" id="category-status-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="updated_at">
                <input type="hidden" name="is_active">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="category-status-title">Xác nhận ẩn/hiện</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p id="category-status-message"></p>
                    <p>Ẩn danh mục sẽ ngăn lựa chọn mới và gửi duyệt tin sử dụng danh mục đó. Dữ liệu đã lưu vẫn được giữ nguyên.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Xác nhận</button>
                </div>
            </form>
        </div>
    </div>
@endsection
