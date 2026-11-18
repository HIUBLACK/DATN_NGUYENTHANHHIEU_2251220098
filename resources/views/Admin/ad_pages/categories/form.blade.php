@extends('Admin.layout.admin')

@section('content')
    @include('Admin.component.category-feedback')
    <div class="card p-4">
        <h2 class="h4 mb-4">{{ $record ? 'Chỉnh sửa danh mục' : 'Thêm danh mục' }}</h2>
        <form method="post" action="{{ $record ? route('admin.'.$categoryType.'.update', $record->id) : route('admin.'.$categoryType.'.store') }}" class="row g-3">
            @csrf
            @if ($record)
                @method('PUT')
                <input type="hidden" name="updated_at" value="{{ $record->getRawOriginal('updated_at') }}">
                <p>Mã: {{ $record->code }}{{ $record->slug ? ' · Slug: '.$record->slug : '' }}</p>
            @else
                <p>Mã và slug (nếu có) được cấp tự động và giữ nguyên khi đổi tên.</p>
            @endif
            <div class="col-md-8">
                <label for="category-name" class="form-label-custom">Tên danh mục *</label>
                <input id="category-name" name="name" value="{{ old('name', $record?->name) }}" class="form-control-custom" required maxlength="120">
            </div>
            <div class="col-md-4">
                <label for="category-order" class="form-label-custom">Thứ tự hiển thị</label>
                <input id="category-order" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $record?->sort_order ?? 0) }}" class="form-control-custom" required>
            </div>
            @if ($categoryType === 'specialties')
                <div class="col-md-8">
                    <label for="category-parent" class="form-label-custom">Nhóm nghề *</label>
                    <select id="category-parent" name="job_category_id" class="form-control-custom" required>
                        <option value="">Chọn nhóm nghề đang hiện</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(old('job_category_id', $record?->job_category_id) == $parent->id)>{{ $parent->name }}{{ $parent->is_active ? '' : ' (Đang ẩn)' }}</option>
                        @endforeach
                    </select>
                    <p class="small mt-2">Chuyên môn đã được tin tham chiếu không thể chuyển nhóm nghề.</p>
                </div>
            @endif
            <div class="col-md-4">
                <label for="category-active" class="form-label-custom">Trạng thái</label>
                <select id="category-active" name="is_active" class="form-control-custom">
                    <option value="1" @selected(old('is_active', $record?->is_active ?? true) == 1)>Đang hiện</option>
                    <option value="0" @selected(old('is_active', $record?->is_active ?? true) == 0)>Đang ẩn</option>
                </select>
            </div>
            <div class="col-12">
                <label for="category-description" class="form-label-custom">Mô tả</label>
                <textarea id="category-description" name="description" rows="4" maxlength="1000" class="form-control-custom">{{ old('description', $record?->description) }}</textarea>
                <p class="small mt-2">Danh mục ẩn không dùng cho lựa chọn mới hoặc gửi duyệt tin. Không xóa dữ liệu lịch sử.</p>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    Lưu
                </button>
                <a href="{{ route('admin.'.$categoryType.'.index') }}" class="btn btn-outline-dark">Hủy</a>
            </div>
        </form>
    </div>
@endsection
