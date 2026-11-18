@extends('employer.layout')

@section('content')
    <div class="card p-4">
        <p>Bản nháp chỉ cần tiêu đề. Hãy lưu nội dung trước khi xem trước hoặc gửi duyệt.</p>
        @if ($version)
            <p>Phiên bản {{ $version->version_number }} · {{ App\Support\JobInput::REVIEWS[$version->review_status->value] }} · Bản công khai: {{ $job->publishedVersion?->version_number ?? 'Chưa có' }}</p>
            @if ($version->moderationLog?->reason)
                <div class="alert alert-warning">Lý do từ chối: {{ $version->moderationLog->reason }}</div>
            @endif
            @if ($version->review_status->value !== 'draft')
                <div class="alert alert-info">Bản đã gửi được giữ nguyên. Nếu được phép, lưu thay đổi sẽ tạo phiên bản nháp mới.</div>
            @endif
        @endif
        @if ($options['industries']->isEmpty() || $options['categories']->isEmpty() || $options['specializations']->isEmpty() || $options['levels']->isEmpty() || $options['skills']->isEmpty())
            <div class="alert alert-warning">Danh mục tuyển dụng chưa đủ dữ liệu. Bạn vẫn có thể lưu nháp, nhưng chưa thể gửi duyệt.</div>
        @endif
        <form method="post" id="real-job-form" action="{{ $job ? route('recruiter.job-update', $job->id) : route('recruiter.job-store') }}">
            @csrf
            @if ($job)
                @method('PUT')
                <input type="hidden" name="version_id" value="{{ old('version_id', $form['version_id']) }}">
                <input type="hidden" name="updated_at" value="{{ old('updated_at', $form['updated_at']) }}">
            @else
                <input type="hidden" name="creation_key" value="{{ $creationKey }}">
            @endif
            <fieldset @disabled($version?->review_status->value === 'pending')>
                <div class="row g-3">
                    @foreach (['title' => ['Tiêu đề *', 150], 'position' => ['Vị trí công việc', 100], 'location' => ['Địa điểm', 150], 'experience_summary' => ['Kinh nghiệm', 100]] as $field => [$label, $max])
                        <div class="col-lg-6 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">{{ $label }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" class="form-control-custom" maxlength="{{ $max }}" value="{{ old($field, $form[$field] ?? '') }}" @required($field === 'title')>
                        </div>
                    @endforeach
                    @foreach (['industry_id' => ['Lĩnh vực', 'industries'], 'job_category_id' => ['Nhóm nghề CNTT', 'categories'], 'job_specialization_id' => ['Chuyên môn CNTT', 'specializations'], 'job_level_id' => ['Cấp bậc', 'levels']] as $field => [$label, $key])
                        <div class="col-lg-6 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">{{ $label }}</label>
                            <select id="{{ $field }}" name="{{ $field }}" class="form-select-custom">
                                <option value="">Chưa chọn</option>
                                @foreach ($options[$key] as $item)
                                    <option value="{{ $item->id }}" data-category="{{ $item->job_category_id ?? '' }}" @selected((string) old($field, $form[$field] ?? '') === (string) $item->id)>
                                        {{ $item->name }}{{ $item->is_active ? '' : ' (đã ẩn)' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    @foreach (['description' => 'Mô tả công việc', 'requirements' => 'Yêu cầu ứng viên', 'benefits' => 'Quyền lợi'] as $field => $label)
                        <div class="col-12 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">{{ $label }}</label>
                            <textarea id="{{ $field }}" name="{{ $field }}" class="form-control-custom" rows="5" maxlength="10000">{{ old($field, $form[$field] ?? '') }}</textarea>
                        </div>
                    @endforeach
                    @foreach (['salary_min' => ['Lương tối thiểu (VND/tháng)', '0.01', '9999999999999.99'], 'salary_max' => ['Lương tối đa (VND/tháng)', '0.01', '9999999999999.99'], 'quantity' => ['Số lượng tuyển', '1', '1000'], 'experience_min' => ['Kinh nghiệm tối thiểu (năm)', '0.1', '999.9'], 'experience_max' => ['Kinh nghiệm tối đa (năm)', '0.1', '999.9']] as $field => [$label, $step, $max])
                        <div class="col-lg-6 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">{{ $label }}</label>
                            <input type="number" id="{{ $field }}" name="{{ $field }}" class="form-control-custom" min="{{ $field === 'quantity' ? '1' : '0' }}" max="{{ $max }}" step="{{ $step }}" value="{{ old($field, $form[$field] ?? '') }}">
                        </div>
                    @endforeach
                    @foreach (['work_mode' => ['Hình thức làm việc', App\Support\JobInput::MODES], 'employment_type' => ['Loại công việc', App\Support\JobInput::TYPES]] as $field => [$label, $choices])
                        <div class="col-lg-6 form-group-custom">
                            <label class="form-label-custom" for="{{ $field }}">{{ $label }}</label>
                            <select id="{{ $field }}" name="{{ $field }}" class="form-select-custom">
                                <option value="">Chưa chọn</option>
                                @foreach ($choices as $value => $text)
                                    <option value="{{ $value }}" @selected(old($field, $form[$field] ?? '') === $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="col-lg-6 form-group-custom">
                        <label class="form-label-custom" for="application_deadline">Hạn ứng tuyển</label>
                        <input type="date" id="application_deadline" name="application_deadline" class="form-control-custom" value="{{ old('application_deadline', $form['application_deadline'] ?? '') }}">
                    </div>
                    <div class="col-12 form-group-custom">
                        <label class="form-label-custom" for="skills">Kỹ năng yêu cầu (có thể chọn nhiều)</label>
                        <select id="skills" name="skills[]" class="form-select-custom" multiple size="6">
                            @foreach ($options['skills'] as $skill)
                                <option value="{{ $skill->id }}" @selected(in_array($skill->id, old('skills', $form['skills'] ?? [])))>{{ $skill->name }}{{ $skill->is_active ? '' : ' (đã ẩn)' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-dark-custom mt-4">
                    <i class="bi bi-save" aria-hidden="true"></i>
                    Lưu bản nháp
                </button>
            </fieldset>
        </form>
        <div class="d-flex gap-2 mt-3">
            <a href="{{ route('recruiter.jobs') }}" class="btn btn-outline-dark">Quay lại</a>
            @if ($job)
                <a href="{{ route('recruiter.job-preview', $job->id) }}" class="btn btn-outline-primary">Xem trước bản đã lưu / Gửi duyệt</a>
            @endif
        </div>
    </div>
@endsection
