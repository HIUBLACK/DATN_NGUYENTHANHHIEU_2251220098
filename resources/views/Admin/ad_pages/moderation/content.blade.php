<h3 class="h5">{{ $content->title }}</h3>
@foreach (['position' => 'Vị trí', 'description' => 'Mô tả', 'requirements' => 'Yêu cầu', 'benefits' => 'Quyền lợi', 'location' => 'Địa điểm', 'experience_summary' => 'Kinh nghiệm'] as $field => $label)
    <h4 class="h6 mt-3">{{ $label }}</h4>
    <p class="company-profile-text">{{ $content->$field }}</p>
@endforeach
<p>Lương: {{ $content->salary_min }}–{{ $content->salary_max }} VND/tháng · {{ $content->salary_negotiable ? 'Có thương lượng' : 'Theo khoảng lương' }}</p>
<p>Số lượng: {{ $content->quantity }} · Hạn: {{ $content->application_deadline?->format('d/m/Y') }}</p>
<p>Kinh nghiệm (năm): {{ $content->experience_min ?? 'Chưa xác định' }}–{{ $content->experience_max ?? 'Chưa xác định' }}</p>
<p>{{ App\Support\JobInput::MODES[$content->work_mode] ?? '' }} · {{ App\Support\JobInput::TYPES[$content->employment_type] ?? '' }}</p>
<p>Lĩnh vực: {{ $content->industry?->name }} · Cấp bậc: {{ $content->level?->name }}</p>
<p>Nhóm nghề: {{ $content->specialization?->category?->name }} · Chuyên môn: {{ $content->specialization?->name }}</p>
<p>Kỹ năng: {{ $content->skills->pluck('name')->implode(', ') }}</p>
