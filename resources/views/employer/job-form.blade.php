@extends('employer.layout')

@section('content')
    <div class="card p-4">
        <p>Nhập đầy đủ thông tin, nhấn Gửi duyệt để kiểm tra nội dung trước khi xác nhận.</p>
        @unless ($job)
            <p>Bạn cũng có thể lưu bản nháp chỉ với tiêu đề để hoàn thiện sau.</p>
        @endunless
        @if ($version)
            <p>Phiên bản {{ $version->version_number }} · {{ App\Support\JobInput::REVIEWS[$version->review_status->value] }} · Bản công khai: {{ $job->publishedVersion?->version_number ?? 'Chưa có' }}</p>
            @if ($version->moderationLog?->reason)
                <div class="alert alert-warning">Lý do từ chối: {{ $version->moderationLog->reason }}</div>
            @endif
            @if ($version->review_status->value !== 'draft')
                <div class="alert alert-info">Bản đã gửi được giữ nguyên. Thay đổi chỉ được gửi kiểm duyệt sau khi bạn xác nhận.</div>
            @endif
        @endif
        @if ($options['industries']->isEmpty() || $options['positions']->isEmpty() || $options['specializations']->isEmpty() || $options['levels']->isEmpty() || $options['skills']->isEmpty())
            <div class="alert alert-warning">Danh mục tuyển dụng chưa đủ dữ liệu để gửi duyệt. Vui lòng liên hệ Admin bổ sung.</div>
        @endif
        <form method="post" id="real-job-form" action="{{ $job ? route('recruiter.job-update', $job->id) : route('recruiter.job-store') }}">
            @csrf
            <input type="hidden" name="intent" id="job-intent" value="submit">
            @if ($job)
                @method('PUT')
                <input type="hidden" name="version_id" value="{{ old('version_id', $form['version_id']) }}">
                <input type="hidden" name="updated_at" value="{{ old('updated_at', $form['updated_at']) }}">
            @else
                <input type="hidden" name="creation_key" value="{{ $creationKey }}">
            @endif
            <fieldset @disabled($version?->review_status->value === 'pending')>
                <div class="row g-3">
                    @foreach (['title' => ['Tiêu đề', 150], 'location' => ['Địa điểm chi tiết', 150]] as $field => [$label, $max])
                        <div class="col-lg-6 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">
                                {{ $label }}
                                @if ($field === 'title')
                                    <span class="text-danger" aria-hidden="true">*</span>
                                @endif
                            </label>
                            <input id="{{ $field }}" name="{{ $field }}" class="form-control-custom" maxlength="{{ $max }}" value="{{ old($field, $form[$field] ?? '') }}" required>
                        </div>
                    @endforeach
                    <div class="col-lg-6 form-group-custom">
                        <label class="form-label-custom" for="province_code">Tỉnh/thành phố</label>
                        <select id="province_code" name="province_code" class="form-select-custom" required>
                            <option value="">Chọn tỉnh/thành phố</option>
                            @foreach (App\Support\Provinces::ALL as $code => $province)
                                <option value="{{ $code }}" @selected((string) old('province_code', $form['province_code'] ?? '') === (string) $code)>{{ $province }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach (['industry_id' => ['Lĩnh vực', 'industries'], 'job_position_id' => ['Vị trí công việc', 'positions'], 'job_specialization_id' => ['Chuyên môn CNTT', 'specializations'], 'job_level_id' => ['Cấp bậc', 'levels']] as $field => [$label, $key])
                        <div class="col-lg-6 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">{{ $label }}</label>
                            <select id="{{ $field }}" name="{{ $field }}" class="form-select-custom" required>
                                <option value="">Chưa chọn</option>
                                @foreach ($options[$key] as $item)
                                    <option value="{{ $item->id }}" data-category="{{ $item->job_category_id ?? '' }}" @selected((string) old($field, $form[$field] ?? '') === (string) $item->id)>
                                        {{ $item->name }}{{ $item->is_active ? '' : ' (đã ẩn)' }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($field === 'job_position_id' && empty($form['job_position_id']) && ! empty($form['position']))
                                <p class="small mt-2">Vị trí đã lưu: {{ $form['position'] }}. Chọn vị trí trong danh mục để gửi phiên bản mới.</p>
                            @endif
                        </div>
                    @endforeach
                    @foreach (['description' => 'Mô tả công việc', 'requirements' => 'Yêu cầu ứng viên', 'benefits' => 'Quyền lợi'] as $field => $label)
                        <div class="col-12 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">{{ $label }}</label>
                            <textarea id="{{ $field }}" name="{{ $field }}" class="form-control-custom" required rows="5" maxlength="10000">{{ old($field, $form[$field] ?? '') }}</textarea>
                        </div>
                    @endforeach
                    @foreach (['salary_min' => ['Lương tối thiểu (VND/tháng)', '0.01', '9999999999999.99'], 'salary_max' => ['Lương tối đa (VND/tháng)', '0.01', '9999999999999.99'], 'quantity' => ['Số lượng tuyển', '1', '1000']] as $field => [$label, $step, $max])
                        <div class="col-lg-6 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">{{ $label }}</label>
                            <input type="number" required id="{{ $field }}" name="{{ $field }}" class="form-control-custom" min="{{ $field === 'quantity' ? '1' : '0' }}" max="{{ $max }}" step="{{ $step }}" value="{{ old($field, $form[$field] ?? '') }}">
                        </div>
                    @endforeach
                    @foreach (['work_mode' => ['Hình thức làm việc', App\Support\JobInput::MODES], 'employment_type' => ['Loại công việc', App\Support\JobInput::TYPES]] as $field => [$label, $choices])
                        <div class="col-lg-6 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">{{ $label }}</label>
                            <select id="{{ $field }}" name="{{ $field }}" class="form-select-custom" required>
                                <option value="">Chưa chọn</option>
                                @foreach ($choices as $value => $text)
                                    <option value="{{ $value }}" @selected(old($field, $form[$field] ?? '') === $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="col-lg-6 form-group-custom">
                        <label class="form-label-custom" for="application_deadline">Hạn ứng tuyển</label>
                        <input type="date" required id="application_deadline" name="application_deadline" class="form-control-custom" value="{{ old('application_deadline', $form['application_deadline'] ?? '') }}">
                    </div>
                    <div class="col-12 form-group-custom">
                        <label class="form-label-custom" for="skills">Kỹ năng yêu cầu (có thể chọn nhiều)</label>
                        <select id="skills" name="skills[]" class="form-select-custom" required multiple size="6">
                            @foreach ($options['skills'] as $skill)
                                <option value="{{ $skill->id }}" @selected(in_array($skill->id, old('skills', $form['skills'] ?? [])))>{{ $skill->name }}{{ $skill->is_active ? '' : ' (đã ẩn)' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if (! $job || $version?->review_status->value === 'draft')
                    <button type="button" id="job-save-draft" class="btn btn-outline-primary mt-4">
                        <i class="bi bi-save" aria-hidden="true"></i>
                        {{ $job ? 'Lưu thay đổi' : 'Lưu bản nháp' }}
                    </button>
                @endif
                <button type="button" id="job-review-open" class="btn-dark-custom mt-4">
                    <i class="bi bi-send" aria-hidden="true"></i>
                    Gửi duyệt
                </button>
            </fieldset>
        </form>
        <div class="d-flex gap-2 mt-3">
            <a href="{{ route('recruiter.jobs') }}" class="btn btn-outline-dark">Quay lại</a>
        </div>
    </div>
    @include('employer.component.job-review-modal')
@endsection
