<?php

namespace App\Support;

class JobInput
{
    public const FIELDS = [
        'title', 'position', 'province_code', 'ward_code', 'description', 'requirements', 'benefits', 'salary_min', 'salary_max',
        'location', 'work_mode', 'employment_type', 'quantity', 'application_deadline',
    ];

    public const STATUSES = ['draft' => 'Bản nháp', 'open' => 'Đang mở', 'paused' => 'Tạm dừng', 'closed' => 'Đã đóng', 'blocked' => 'Bị khóa'];

    public const REVIEWS = ['draft' => 'Chưa gửi duyệt', 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối'];

    public const MODES = ['onsite' => 'Tại văn phòng', 'remote' => 'Từ xa', 'hybrid' => 'Kết hợp'];

    public const TYPES = ['full_time' => 'Toàn thời gian', 'part_time' => 'Bán thời gian', 'internship' => 'Thực tập'];

    public static function rules(array $data, bool $submit = false): array
    {
        $required = $submit ? 'required' : 'nullable';
        $rules = ['title' => ['required', 'string', 'max:150', 'regex:/[^\s\p{Z}]/u']];
        foreach (['position' => 120, 'description' => 10000, 'requirements' => 10000, 'benefits' => 10000, 'location' => 150] as $field => $length) {
            $rules[$field] = [$required, 'string', 'max:'.$length];
        }
        foreach (['salary_min', 'salary_max'] as $field) {
            $rules[$field] = [$required, 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'];
        }
        if (isset($data['salary_min'], $data['salary_max'])) {
            $rules['salary_max'][] = 'gte:salary_min';
        }
        $rules['province_code'] = [$required, 'string', 'in:'.implode(',', array_keys(Provinces::ALL))];
        $rules['ward_code'] = [$required, 'string', 'size:5', VietnamAddress::wardRule($data['province_code'] ?? null)];
        $rules['job_position_id'] = [$required, 'integer', 'min:1'];
        foreach (['industry_id', 'job_specialization_id', 'job_level_id'] as $field) {
            $rules[$field] = [$required, 'integer', 'min:1'];
        }
        $rules['work_mode'] = [$required, 'in:'.implode(',', array_keys(self::MODES))];
        $rules['employment_type'] = [$required, 'in:'.implode(',', array_keys(self::TYPES))];
        $rules['quantity'] = [$required, 'integer', 'between:1,1000'];
        $rules['application_deadline'] = [$required, 'date_format:Y-m-d'];
        if ($submit) {
            $rules['application_deadline'][] = 'after_or_equal:'.now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        }
        $rules['skills'] = [$submit ? 'required' : 'sometimes', 'array', 'max:100'];
        if ($submit) {
            $rules['skills'][] = 'min:1';
        }
        $rules['skills.*'] = ['integer', 'min:1', 'distinct'];

        return $rules;
    }
}
