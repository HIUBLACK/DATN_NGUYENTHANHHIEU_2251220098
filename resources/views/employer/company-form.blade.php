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
            <p class="small text-muted-green">
                Các trường có <span class="text-danger">*</span> là bắt buộc.
                Email và số điện thoại chỉ hiển thị cho chủ hồ sơ, không công khai trên trang doanh nghiệp.
            </p>
            <fieldset class="mb-4">
                <legend class="h5 mb-3">Thông tin cơ bản</legend>
                <div class="row g-3">
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="name" class="form-label-custom">
                            Tên doanh nghiệp <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="name" name="name" type="text" value="{{ old('name', $companyForm['name'] ?? '') }}" class="form-control-custom" required maxlength="150">
                    </div>
                    <div class="col-12 form-group-custom">
                        <label for="description" class="form-label-custom">Giới thiệu doanh nghiệp</label>
                        <textarea id="description" name="description" class="form-control-custom" rows="4" maxlength="10000">{{ old('description', $companyForm['description'] ?? '') }}</textarea>
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
                        <select id="company_type" name="company_type" class="form-select-custom">
                            <option value="">Chọn mô hình công ty</option>
                            @foreach ($companyTypes as $type)
                                <option value="{{ $type }}" @selected(old('company_type', $companyForm['company_type'] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="country" class="form-label-custom">Quốc gia</label>
                        <select id="country" name="country" class="form-select-custom">
                            <option value="">Chọn quốc gia / vùng lãnh thổ</option>
                            @foreach ($companyCountries as $country)
                                <option value="{{ $country }}" @selected(old('country', $companyForm['country'] ?? '') === $country)>{{ $country }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="website" class="form-label-custom">Website</label>
                        <input id="website" name="website" type="url" value="{{ old('website', $companyForm['website'] ?? '') }}" class="form-control-custom" maxlength="255" pattern="https?://.+" placeholder="https://example.com">
                        <label class="d-block mt-2">
                            <input name="no_website" type="hidden" value="0">
                            <input id="no-website" name="no_website" type="checkbox" value="1" @checked(old('no_website', $companyRecord && empty($companyForm['website'])))>
                            Không có website
                        </label>
                    </div>
                </div>
            </fieldset>
            <fieldset class="mb-4">
                <legend class="h5 mb-3">Liên hệ</legend>
                <div class="row g-3">
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="email" class="form-label-custom">
                            Email <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="email" name="email" type="email" value="{{ old('email', $companyForm['email'] ?? '') }}" class="form-control-custom" required maxlength="150">
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="phone" class="form-label-custom">
                            Số điện thoại <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', $companyForm['phone'] ?? '') }}" class="form-control-custom" required maxlength="30" pattern="[0-9+() .-]{8,30}">
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="address" class="form-label-custom">
                            Địa chỉ <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="address" name="address" type="text" value="{{ old('address', $companyForm['address'] ?? '') }}" class="form-control-custom" required maxlength="255">
                    </div>
                </div>
            </fieldset>
            <fieldset class="mb-4">
                <legend class="h5 mb-3">Môi trường làm việc</legend>
                <div class="row g-3">
                    <div class="col-12 form-group-custom">
                        <label for="work_environment" class="form-label-custom">Giới thiệu môi trường làm việc</label>
                        <textarea id="work_environment" name="work_environment" class="form-control-custom" rows="5" maxlength="3000">{{ old('work_environment', $companyForm['work_environment'] ?? '') }}</textarea>
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="working_from" class="form-label-custom">Làm việc từ thứ</label>
                        <select id="working_from" name="working_from" class="form-select-custom">
                            <option value="">Chọn ngày bắt đầu</option>
                            @foreach (App\Support\CompanyOptions::DAYS as $day => $label)
                                <option value="{{ $day }}" @selected(old('working_from', $companyForm['working_from'] ?? '') == $day)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-lg-6 form-group-custom">
                        <label for="working_to" class="form-label-custom">Đến thứ</label>
                        <select id="working_to" name="working_to" class="form-select-custom">
                            <option value="">Chọn ngày kết thúc</option>
                            @foreach (App\Support\CompanyOptions::DAYS as $day => $label)
                                <option value="{{ $day }}" @selected(old('working_to', $companyForm['working_to'] ?? '') == $day)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if (! empty($companyForm['working_hours']))
                            <p class="small mt-2">Đã lưu: {{ $companyForm['working_hours'] }}. Để trống hai lựa chọn nếu muốn giữ nguyên.</p>
                        @endif
                    </div>
                    <div class="col-12 form-group-custom">
                        <label for="ot_policy" class="form-label-custom">Chính sách làm thêm giờ (OT)</label>
                        <select id="ot_policy" name="ot_policy" class="form-select-custom">
                            <option value="">Chọn chính sách làm thêm giờ</option>
                            @foreach (App\Support\CompanyOptions::OVERTIME as $option)
                                <option value="{{ $option }}" @selected(old('ot_policy', $companyForm['ot_policy'] ?? '') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @if (! empty($companyForm['ot_policy']) && ! in_array($companyForm['ot_policy'], App\Support\CompanyOptions::OVERTIME, true))
                            <p class="small mt-2">Đã lưu: {{ $companyForm['ot_policy'] }}. Chọn một lựa chọn để cập nhật; để trống để giữ nguyên.</p>
                        @endif
                    </div>
                </div>
            </fieldset>
            <fieldset class="mb-4">
                <legend class="h5 mb-3">Hình ảnh doanh nghiệp</legend>
                <p class="small text-muted-green">Chỉ JPG, PNG, WebP; tối đa 5000 × 5000 pixel. Logo 2 MB, ảnh văn phòng 3 MB/ảnh; tối đa 6 ảnh. Ảnh luôn nằm trong khung cố định.</p>
                @foreach (['logo' => 'Logo', 'office_images' => 'Hình ảnh văn phòng'] as $field => $label)
                    <div class="mb-4">
                        <label for="company-{{ $field }}" class="form-label-custom">{{ $label }}</label>
                        <input type="file" id="company-{{ $field }}" name="{{ $field === 'office_images' ? 'office_images[]' : $field }}"
                            accept="image/jpeg,image/png,image/webp" class="form-control" data-company-image="{{ $field }}"
                            @if ($field === 'office_images') multiple @endif>
                        <div class="company-image-previews mt-3" id="preview-{{ $field }}" aria-live="polite"></div>
                        @if ($companyRecord && $field !== 'office_images' && $companyRecord[$field])
                            <div class="company-image-item mt-2" data-saved-image="logo">
                                <img src="{{ route('recruiter.company-media', [$companyRecord['id'], $field]) }}" alt="{{ $label }}" class="company-upload-preview">
                                <input type="hidden" name="remove_logo" value="1" data-image-removal @disabled(! old('remove_logo'))>
                                <button type="button" class="company-image-remove" data-remove-saved-image aria-label="Bỏ logo khi lưu" @disabled(! $canSaveCompany)>&times;</button>
                            </div>
                        @endif
                    </div>
                @endforeach
                @if ($companyRecord)
                    <div class="company-image-previews">
                        @foreach ($companyRecord['office_images'] ?? [] as $index => $path)
                            <div class="company-image-item" data-saved-image="office">
                                <img src="{{ route('recruiter.company-media', [$companyRecord['id'], 'office-'.$index]) }}" alt="Ảnh văn phòng {{ $index + 1 }}" class="company-upload-preview">
                                <input type="hidden" name="remove_office_images[]" value="{{ $index }}" data-image-removal @disabled(! in_array((string) $index, old('remove_office_images', [])))>
                                <button type="button" class="company-image-remove" data-remove-saved-image aria-label="Bỏ ảnh văn phòng khi lưu" @disabled(! $canSaveCompany)>&times;</button>
                            </div>
                        @endforeach
                    </div>
                @endif
                <p class="small">Nhấn × để bỏ ảnh. Ảnh đã lưu chỉ bị xóa khi bạn bấm Lưu hồ sơ.</p>
            </fieldset>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn-dark-custom" type="submit" @disabled(! $canSaveCompany)>
                    <i class="bi bi-check2" aria-hidden="true"></i>
                    Lưu hồ sơ
                </button>
                <a href="{{ route('recruiter.company') }}" class="btn btn-light">Hủy</a>
            </div>
        </form>
    </div>
@endsection
