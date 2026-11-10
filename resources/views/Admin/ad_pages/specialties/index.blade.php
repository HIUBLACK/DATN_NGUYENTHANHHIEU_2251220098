@extends('Admin.layout.admin')

@section('content')
    <div class="alert alert-light border">
        <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
        Danh mục ẩn vẫn được lưu và hiển thị trong Admin. Chỉ nhóm nghề và chuyên môn đang hiện được dùng cho dữ liệu mới.
    </div>
    @include('Admin.component.list')
    @include('Admin.component.category-modal')
@endsection
