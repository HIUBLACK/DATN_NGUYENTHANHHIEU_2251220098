@extends('Admin.layout.admin')

@section('content')
    <p class="alert alert-light border">Danh mục ẩn vẫn được giữ trong Admin và không xuất hiện trong menu khám phá việc làm. Thao tác chỉ lưu trong phiên demo.</p>
    @include('Admin.component.list')
    @include('Admin.component.category-modal')
@endsection
