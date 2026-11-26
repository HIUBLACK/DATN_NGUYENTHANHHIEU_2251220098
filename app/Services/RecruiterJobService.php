<?php

namespace App\Services;

use App\Support\JobCatalog;

use App\Enums\JobReviewStatus;
use App\Enums\JobStatus;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobVersion;
use App\Models\JobVersionSkill;
use App\Models\User;
use App\Support\JobInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecruiterJobService
{
    public function saveForReview(User $actor, array $data, ?int $jobId = null): Job
    {
        return DB::transaction(function () use ($actor, $data, $jobId) {
            if ($jobId !== null) {
                [, $record] = $this->lockJob($actor, $jobId, 'update');
                $original = $this->editableVersion($record, $data);
                $saved = $this->formData($original);
                $fields = array_merge(array_diff(JobInput::FIELDS, ['position']), ['job_position_id', 'industry_id', 'job_specialization_id', 'job_level_id', 'skills']);
                $changed = false;
                foreach ($fields as $field) {
                    $old = $saved[$field] ?? null;
                    $new = $data[$field] ?? null;
                    if ($field === 'skills') {
                        $old = array_map('intval', $old ?? []);
                        $new = array_map('intval', $new ?? []);
                        sort($old);
                        sort($new);
                    } elseif (in_array($field, ['salary_min', 'salary_max'], true) && $old !== null && $new !== null) {
                        $old = bcadd((string) $old, '0', 2);
                        $new = bcadd((string) $new, '0', 2);
                    } else {
                        $old = trim(str_replace("\r\n", "\n", (string) $old));
                        $new = trim(str_replace("\r\n", "\n", (string) $new));
                    }
                    if ($old !== $new) {
                        $changed = true;
                        break;
                    }
                }
                if (! $changed) {
                    throw ValidationException::withMessages(['job' => 'Bạn chưa thay đổi nội dung tin tuyển dụng.']);
                }
            }
            $job = $jobId === null ? $this->create($actor, $data) : $this->update($actor, $jobId, $data);
            $version = $this->latest($job);
            $this->submit($actor, $job->id, [
                'version_id' => $version->id,
                'updated_at' => $version->getRawOriginal('updated_at'),
            ]);

            return $job;
        });
    }

    public function reviewBaseline(Job $job): array
    {
        $version = $job->publishedVersion ?? $this->latest($job);
        $data = $this->formData($version);
        $labels = [
            'ward_code' => \App\Support\VietnamAddress::ward($version->ward_code)['name'] ?? null,
            'job_position_id' => $version->position,
            'industry_id' => $version->industry?->name,
            'job_specialization_id' => $version->specialization?->name,
            'job_level_id' => $version->level?->name,
            'skills' => $version->skills->pluck('name')->all(),
        ];

        return [
            'label' => ($job->published_version_id ? 'Bản công khai' : 'Bản đã lưu').' V'.$version->version_number,
            'values' => $data,
            'savedValues' => $this->formData($this->latest($job)),
            'labels' => $labels,
        ];
    }

    public function latest(Job $job): JobVersion
    {
        return $job->versions()->with(['skillRequirements', 'moderationLog'])
            ->orderByDesc('version_number')->firstOrFail();
    }

    public function create(User $actor, array $data): Job
    {
        return DB::transaction(function () use ($actor, $data) {
            $user = User::lockForUpdate()->findOrFail($actor->id);
            $company = Company::where('recruiter_id', $user->id)->lockForUpdate()->first();
            Gate::forUser($user)->authorize('create', [Job::class, $company]);
            $job = new Job;
            $job->company_id = $company->id;
            $job->status = JobStatus::Draft;
            $job->save();
            $version = new JobVersion;
            $version->job_id = $job->id;
            $version->version_number = 1;
            $version->review_status = JobReviewStatus::Draft;
            $this->saveContent($version, $data);
            $this->log($user, $job, null, 'job.create');

            return $job;
        });
    }

    public function update(User $actor, int $id, array $data): Job
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            [$user, $job] = $this->lockJob($actor, $id, 'update');
            $version = $this->editableVersion($job, $data);
            if ($version->review_status !== JobReviewStatus::Draft) {
                // Approved/rejected là lịch sử bất biến; POST/PUT mới tạo bản sao để chỉnh sửa.
                // Bản mới không được kế thừa kết quả/token AI của nội dung phiên bản cũ.
                $copy = $version->replicate([
                    'pending_slot', 'submitted_at', 'reviewed_at',
                    'ai_analysis_result', 'ai_analysis_status', 'ai_processing_token', 'ai_attempt_count',
                    'ai_started_at', 'ai_lease_expires_at', 'ai_next_retry_at', 'ai_analyzed_at',
                    'ai_model_version', 'ai_error_code',
                ]);
                $copy->version_number = $version->version_number + 1;
                $copy->review_status = JobReviewStatus::Draft;
                $this->saveContent($copy, $data, $version);
            } else {
                $this->saveContent($version, $data, $version);
            }
            $job->touch();

            return $job;
        });
    }

    public function submit(User $actor, int $id, array $data): void
    {
        DB::transaction(function () use ($actor, $id, $data) {
            [$user, $job] = $this->lockJob($actor, $id, 'update');
            $version = $this->editableVersion($job, $data);
            if ($version->review_status !== JobReviewStatus::Draft) {
                throw ValidationException::withMessages(['job' => 'Chỉ bản nháp mới được gửi duyệt.']);
            }
            $this->validateForReview($version);
            $version->review_status = JobReviewStatus::Pending;
            $version->submitted_at = now();
            $version->save();
            $job->touch();
        });
    }

    public function transition(User $actor, int $id, string $action): void
    {
        DB::transaction(function () use ($actor, $id, $action) {
            [$user, $job] = $this->lockJob($actor, $id, 'operate');
            $target = match ($action) {
                'pause' => JobStatus::Paused,
                'reopen' => JobStatus::Open,
                'close' => JobStatus::Closed,
            };
            if ($job->status === $target) {
                return;
            }
            $old = $job->status;
            $allowed = ($old === JobStatus::Open && in_array($target, [JobStatus::Paused, JobStatus::Closed], true))
                || ($old === JobStatus::Paused && in_array($target, [JobStatus::Open, JobStatus::Closed], true));
            if (! $allowed) {
                throw ValidationException::withMessages(['job' => 'Trạng thái hiện tại không cho phép thao tác này.']);
            }
            if ($target === JobStatus::Open) {
                $job->status = JobStatus::Open;
                $job->setRelation('publishedVersion', $job->publishedVersionQuery()->lockForUpdate()->first());
                if (! Gate::forUser($user)->allows('viewPublic', $job)) {
                    throw ValidationException::withMessages(['job' => 'Không thể mở lại tin chưa được duyệt, đã hết hạn hoặc không còn đủ điều kiện công khai.']);
                }
            }
            $job->status = $target;
            $job->save();
            $this->log($user, $job, $old, $action === 'reopen' ? 'job.open' : 'job.'.$action);
        });
    }

    public function formData(JobVersion $version): array
    {
        $data = $version->only(JobInput::FIELDS);
        $data['application_deadline'] = $version->application_deadline?->format('Y-m-d');
        $data['job_position_id'] = $version->job_position_id;
        $data['industry_id'] = $version->industry_id;
        $data['job_level_id'] = $version->job_level_id;
        $data['job_specialization_id'] = $version->job_specialization_id;
        $data['job_category_id'] = $version->specialization?->job_category_id;
        $data['skills'] = $version->skills->pluck('id')->all();
        $data['version_id'] = $version->id;
        $data['updated_at'] = $version->getRawOriginal('updated_at');

        return $data;
    }

    public function validateForReview(JobVersion $version): void
    {
        $input = $this->formData($version);
        $rules = JobInput::rules($input, true);
        // Phiên bản chờ duyệt trước cập nhật địa chỉ vẫn được kiểm duyệt nguyên trạng.
        if ($version->review_status === JobReviewStatus::Pending && $version->ward_code === null) {
            $rules['ward_code'][0] = 'nullable';
        }
        // Pending versions created before the catalog rollout remain reviewable without rewriting history.
        if ($version->review_status === JobReviewStatus::Pending && ! $version->job_position_id && ! $version->province_code) {
            $rules['job_position_id'][0] = 'nullable';
            $rules['province_code'][0] = 'nullable';
        }
        Validator::make($input, $rules)->validate();
        $this->validateCatalogs($input);
    }

    public function catalogOptions(): array
    {
        $options = [];
        foreach (['positions', 'industries', 'levels', 'categories', 'specializations', 'skills'] as $group) {
            $options[$group] = JobCatalog::all($group);
        }

        return $options;
    }

    private function lockJob(User $actor, int $id, string $ability): array
    {
        $user = User::lockForUpdate()->findOrFail($actor->id);
        $company = Company::where('recruiter_id', $user->id)->lockForUpdate()->first();
        $job = Job::where('company_id', $company?->id)->lockForUpdate()->findOrFail($id);
        $job->setRelation('company', $company);
        $company->setRelation('recruiter', $user);
        Gate::forUser($user)->authorize($ability, $job);

        return [$user, $job];
    }

    private function editableVersion(Job $job, array $data): JobVersion
    {
        $version = $job->versions()->orderByDesc('version_number')->lockForUpdate()->firstOrFail();
        if ($job->versions()->where('review_status', JobReviewStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['job' => 'Tin đang có phiên bản chờ duyệt. Không thể sửa hoặc gửi thêm.']);
        }
        if ((int) $data['version_id'] !== $version->id || $data['updated_at'] !== $version->getRawOriginal('updated_at')) {
            throw ValidationException::withMessages(['job' => 'Nội dung đã thay đổi. Vui lòng tải lại trang trước khi lưu.']);
        }

        return $version;
    }

    private function saveContent(JobVersion $version, array $data, ?JobVersion $previous = null): void
    {
        $this->validateCatalogs($data);
        $position = isset($data['job_position_id']) ? JobCatalog::find('positions', $data['job_position_id']) : null;
        // Preserve the version's snapshot when keeping the same position after a catalog rename.
        $data['position'] = $position && $previous?->job_position_id === $position->id
            ? $previous->position : $position?->name;
        foreach (JobInput::FIELDS as $field) {
            $version->$field = $data[$field] ?? null;
        }
        foreach (['job_position_id', 'industry_id', 'job_level_id', 'job_specialization_id'] as $field) {
            $version->$field = $data[$field] ?? null;
        }
        $version->updated_at = now();
        $version->save();
        $skillIds = array_map('intval', $data['skills'] ?? []);
        $version->skillRequirements()->whereNotIn('skill_id', $skillIds)->delete();
        foreach ($skillIds as $skillId) {
            $pivot = $version->skillRequirements()->where('skill_id', $skillId)->first() ?? new JobVersionSkill;
            $pivot->job_version_id = $version->id;
            $pivot->skill_id = $skillId;
            $pivot->is_required = true;
            $pivot->save();
        }
    }

    private function validateCatalogs(array $data): void
    {
        $fields = [
            'job_position_id' => 'positions',
            'industry_id' => 'industries',
            'job_level_id' => 'levels',
            'job_specialization_id' => 'specializations',
        ];
        foreach ($fields as $field => $group) {
            if (isset($data[$field]) && ! JobCatalog::find($group, $data[$field])) {
                throw ValidationException::withMessages([$field => 'Lựa chọn không hợp lệ. Vui lòng chọn lại.']);
            }
        }
        foreach ($data['skills'] ?? [] as $id) {
            if (! JobCatalog::find('skills', $id)) {
                throw ValidationException::withMessages(['skills' => 'Kỹ năng không hợp lệ.']);
            }
        }
    }

    private function log(User $actor, Job $job, ?JobStatus $old, string $action): void
    {
        $log = new ActivityLog;
        $log->actor_id = $actor->id;
        $log->subject_type = 'job';
        $log->subject_id = $job->id;
        $log->action = $action;
        $log->old_values = $old ? ['status' => $old->value, 'status_before_block' => null] : null;
        $log->new_values = ['status' => $job->status->value, 'status_before_block' => null];
        $log->save();
    }
}
