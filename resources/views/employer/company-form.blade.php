@extends('employer.layout')

@section('content')
    <div class="card p-4">
        <form id="company-form" novalidate>
            <p class="small text-muted-green">Các trường có * là bắt buộc. Chỉ dùng dữ liệu mẫu để thử giao diện.</p>
            <div class="mb-4">
                <label for="company-logo" class="form-label-custom">Logo doanh nghiệp (PNG, JPG hoặc WebP; tối đa 2 MB)</label>
                <input type="file" id="company-logo" accept="image/png,image/jpeg,image/webp" class="form-control">
                <img id="company-logo-preview" class="recruiter-logo-preview mt-3" alt="Xem trước logo" hidden>
            </div>
            <div class="row g-3">
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="name" class="form-label-custom">Tên doanh nghiệp *</label>
                    <input id="name" name="name" type="text" class="form-control-custom" required maxlength="150">
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="website" class="form-label-custom">Website</label>
                    <input id="website" name="website" type="url" class="form-control-custom" >
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="email" class="form-label-custom">Email liên hệ *</label>
                    <input id="email" name="email" type="email" class="form-control-custom" required >
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="phone" class="form-label-custom">Số điện thoại *</label>
                    <input id="phone" name="phone" type="tel" class="form-control-custom" required pattern="[0-9+() .-]{8,20}">
                </div>
                <div class="col-12 col-lg-6 form-group-custom">
                    <label for="address" class="form-label-custom">Địa chỉ *</label>
                    <input id="address" name="address" type="text" class="form-control-custom" required maxlength="250">
                </div>
                <div class="col-12  form-group-custom">
                    <label for="description" class="form-label-custom">Mô tả doanh nghiệp *</label>
                    <textarea id="description" name="description" class="form-control-custom" rows="5" required maxlength="5000"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn-dark-custom" type="submit">Lưu thông tin (demo)</button>
                <a href="{{ route('recruiter.company') }}" class="btn btn-light">Hủy</a>
            </div>
        </form>
    </div>
@endsection
