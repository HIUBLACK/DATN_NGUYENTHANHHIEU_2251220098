@extends('employer.layout')

@section('content')
    <div class="card p-4">
        @unless ($canSaveCompany)
            <div class="alert alert-warning">
                Doanh nghiệp đang bị khóa. Bạn có thể xem thông tin, nhưng không thể lưu thay đổi.
            </div>
        @endunless
        <form id="company-form" method="post" enctype="multipart/form-data"
            action="{{ $companyRecord ? route('recruiter.company-update', $companyRecord['id']) : route('recruiter.company-store') }}">
            @csrf
            @if ($companyRecord)
                @method('PUT')
                <input type="hidden" name="updated_at" value="{{ old('updated_at', $companyForm['updated_at']) }}">
            @endif
            <p class="small text-muted-green">Các trường có * là bắt buộc. Email và số điện thoại chỉ hiển thị cho chủ hồ sơ, không công khai trên trang doanh nghiệp.</p>
            <fieldset class="mb-4">
                <legend class="h5 mb-3">Thông tin cơ bản</legend>
                <div class="row g-3">
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="name" class="form-label-custom">Tên doanh nghiệp *</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $companyForm['name'] ?? '') }}" class="form-control-custom" required maxlength="150">
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="short_name" class="form-label-custom">Tên viết tắt</label>
                        <input id="short_name" name="short_name" type="text" value="{{ old('short_name', $companyForm['short_name'] ?? '') }}" class="form-control-custom" maxlength="80">
                    </div>
                    <div class="col-12 form-group-custom">
                        <label for="summary" class="form-label-custom">Mô tả ngắn</label>
                        <textarea id="summary" name="summary" class="form-control-custom" rows="4" maxlength="500">{{ old('summary', $companyForm['summary'] ?? '') }}</textarea>
                    </div>
                    <div class="col-12 form-group-custom">
                        <label for="description" class="form-label-custom">Giới thiệu chi tiết</label>
                        <textarea id="description" name="description" class="form-control-custom" rows="4" maxlength="10000">{{ old('description', $companyForm['description'] ?? '') }}</textarea>
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="founded_year" class="form-label-custom">Năm thành lập</label>
                        <input id="founded_year" name="founded_year" type="number" value="{{ old('founded_year', $companyForm['founded_year'] ?? '') }}" class="form-control-custom" min="1800" max="{{ now('Asia/Ho_Chi_Minh')->year }}" step="1">
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="size" class="form-label-custom">Quy mô nhân sự</label>
                        <select id="size" name="size" class="form-select-custom">
                            <option value="">Chọn quy mô</option>
                            @foreach (['1–10', '11–50', '51–200', '201–500', '501–1000', 'Trên 1000'] as $size)
                                <option value="{{ $size }}" @selected(old('size', $companyForm['size'] ?? '') === $size)>{{ $size }} nhân sự</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </fieldset>
            <fieldset class="mb-4">
                <legend class="h5 mb-3">Thông tin hoạt động</legend>
                <div class="row g-3">
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="industry_id" class="form-label-custom">Lĩnh vực hoạt động</label>
                        <select id="industry_id" name="industry_id" class="form-select-custom">
                            <option value="">Chọn lĩnh vực</option>
                            @foreach ($industries as $industry)
                                <option value="{{ $industry->id }}" @selected((string) old('industry_id', $companyForm['industry_id'] ?? '') === (string) $industry->id)>
                                    {{ $industry->name }}{{ $industry->is_active ? '' : ' (đã ẩn — giá trị đã lưu)' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($industries->isEmpty())
                            <p class="small text-muted-green mt-2">Chưa có lĩnh vực hoạt động để chọn. Bạn vẫn có thể lưu hồ sơ.</p>
                        @endif
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="company_type" class="form-label-custom">Mô hình công ty</label>
                        <input id="company_type" name="company_type" type="text" value="{{ old('company_type', $companyForm['company_type'] ?? '') }}" class="form-control-custom" maxlength="80">
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="country" class="form-label-custom">Quốc gia</label>
                        <input id="country" name="country" type="text" value="{{ old('country', $companyForm['country'] ?? '') }}" class="form-control-custom" maxlength="80">
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="website" class="form-label-custom">Website</label>
                        <input id="website" name="website" type="url" value="{{ old('website', $companyForm['website'] ?? '') }}" class="form-control-custom" maxlength="255" pattern="https?://.+" placeholder="https://example.com">
                    </div>
                </div>
            </fieldset>
            <fieldset class="mb-4">
                <legend class="h5 mb-3">Liên hệ</legend>
                <div class="row g-3">
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="email" class="form-label-custom">Email *</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $companyForm['email'] ?? '') }}" class="form-control-custom" required maxlength="150">
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="phone" class="form-label-custom">Số điện thoại *</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', $companyForm['phone'] ?? '') }}" class="form-control-custom" required maxlength="30" pattern="[0-9+() .-]{8,30}">
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="address" class="form-label-custom">Địa chỉ *</label>
                        <input id="address" name="address" type="text" value="{{ old('address', $companyForm['address'] ?? '') }}" class="form-control-custom" required maxlength="255">
                    </div>
                </div>
            </fieldset>
            <fieldset class="mb-4">
                <legend class="h5 mb-3">Môi trường làm việc</legend>
                <div class="row g-3">
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="working_hours" class="form-label-custom">Thời gian làm việc</label>
                        <input id="working_hours" name="working_hours" type="text" value="{{ old('working_hours', $companyForm['working_hours'] ?? '') }}" class="form-control-custom" maxlength="255">
                    </div>
                    <div class="col-12 form-group-custom">
                        <label for="ot_policy" class="form-label-custom">Chính sách làm thêm giờ (OT)</label>
                        <textarea id="ot_policy" name="ot_policy" class="form-control-custom" rows="4" maxlength="2000">{{ old('ot_policy', $companyForm['ot_policy'] ?? '') }}</textarea>
                    </div>
                    <div class="col-12 form-group-custom">
                        <label for="benefits" class="form-label-custom">Phúc lợi nổi bật</label>
                        <textarea id="benefits" name="benefits" class="form-control-custom" rows="4" maxlength="3000">{{ old('benefits', $companyForm['benefits'] ?? '') }}</textarea>
                    </div>
                    <div class="col-12 form-group-custom">
                        <label for="technologies" class="form-label-custom">Công nghệ sử dụng</label>
                        <textarea id="technologies" name="technologies" class="form-control-custom" rows="4" maxlength="2000">{{ old('technologies', $companyForm['technologies'] ?? '') }}</textarea>
                    </div>
                </div>
            </fieldset>
            <fieldset class="mb-4">
                <legend class="h5 mb-3">Hình ảnh doanh nghiệp</legend>
                <p class="small text-muted-green">Chỉ JPG, PNG, WebP; tối đa 5000 × 5000 pixel. Logo 2 MB, ảnh bìa 5 MB, ảnh văn phòng 3 MB/ảnh; tối đa 6 ảnh văn phòng.</p>
                @foreach (['logo' => 'Logo', 'cover' => 'Ảnh bìa', 'office_images' => 'Hình ảnh văn phòng'] as $field => $label)
                    <div class="mb-4">
                        <label for="company-{{ $field }}" class="form-label-custom">{{ $label }}</label>
                        <input type="file" id="company-{{ $field }}" name="{{ $field === 'office_images' ? 'office_images[]' : $field }}"
                            accept="image/jpeg,image/png,image/webp" class="form-control" data-company-image="{{ $field }}"
                            @if ($field === 'office_images') multiple @endif>
                        <div class="company-image-previews mt-3" id="preview-{{ $field }}" aria-live="polite"></div>
                        @if ($companyRecord && $field !== 'office_images' && $companyRecord[$field])
                            <img src="{{ route('recruiter.company-media', [$companyRecord['id'], $field]) }}" alt="{{ $label }} đã lưu" class="company-upload-preview mt-2">
                            <label class="d-block mt-2">
                                <input type="checkbox" name="remove_{{ $field }}" value="1" @checked(old('remove_'.$field))>
                                Xóa {{ mb_strtolower($label) }} hiện tại
                            </label>
                        @endif
                    </div>
                @endforeach
                @if ($companyRecord)
                    <div class="company-image-previews">
                        @foreach ($companyRecord['office_images'] ?? [] as $index => $path)
                            <label>
                                <img src="{{ route('recruiter.company-media', [$companyRecord['id'], 'office-'.$index]) }}" alt="Ảnh văn phòng {{ $index + 1 }}" class="company-upload-preview">
                                <span class="d-block mt-2">
                                    <input type="checkbox" name="remove_office_images[]" value="{{ $index }}" @checked(in_array((string) $index, old('remove_office_images', [])))>
                                    Xóa ảnh {{ $index + 1 }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <p id="company-image-error" class="text-danger" role="alert" hidden></p>
            </fieldset>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn-dark-custom" type="submit" @disabled(! $canSaveCompany)>
                    <i class="bi bi-check2" aria-hidden="true"></i>
                    Lưu hồ sơ
                </button>
                <a href="{{ route('recruiter.company') }}" class="btn btn-light">Hủy</a>
                @if ($companyRecord && $companyRecord['status'] === 'active')
                    <a href="{{ route('companies.show', $companyRecord['id']) }}" class="btn btn-outline-dark">Xem trang công khai</a>
                @endif
            </div>
        </form>
    </div>
@endsection
