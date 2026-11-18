<?php

namespace App\Services;

use App\Enums\JobReviewStatus;
use App\Enums\JobStatus;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Industry;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobSpecialization;
use App\Models\JobVersion;
use App\Models\JobVersionSkill;
use App\Models\Skill;
use App\Models\User;
use App\Support\JobInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecruiterJobService
{
    public function latest(Job $job): JobVersion
    {
        return $job->versions()->with(['skills', 'specialization.category', 'industry', 'level', 'moderationLog'])
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
                $copy = $version->replicate(['pending_slot', 'submitted_at', 'reviewed_at']);
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
        Validator::make($input, JobInput::rules($input, true))->validate();
        $this->validateCatalogs($input, $version, true);
    }

    public function catalogOptions(?JobVersion $version = null): array
    {
        $selected = $version ? $this->formData($version) : [];
        $options = [];
        foreach (['industries' => [Industry::class, 'industry_id'], 'levels' => [JobLevel::class, 'job_level_id'], 'categories' => [JobCategory::class, 'job_category_id']] as $key => [$model, $field]) {
            $options[$key] = $model::where('is_active', true)->orWhere('id', $selected[$field] ?? 0)->orderBy('name')->get();
        }
        $options['specializations'] = JobSpecialization::with('category')->where(function ($query) {
            $query->where('is_active', true)->whereHas('category', fn ($category) => $category->where('is_active', true));
        })->orWhere('id', $selected['job_specialization_id'] ?? 0)->orderBy('name')->get();
        $options['skills'] = Skill::where('is_active', true)->orWhereIn('id', $selected['skills'] ?? [])->orderBy('name')->get();

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
        $this->validateCatalogs($data, $previous);
        foreach (JobInput::FIELDS as $field) {
            $version->$field = $data[$field] ?? null;
        }
        foreach (['industry_id', 'job_level_id', 'job_specialization_id'] as $field) {
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

    private function validateCatalogs(array $data, ?JobVersion $previous, bool $submit = false): void
    {
        $old = $previous ? $this->formData($previous) : [];
        foreach (['job_category_id' => JobCategory::class, 'industry_id' => Industry::class, 'job_level_id' => JobLevel::class, 'job_specialization_id' => JobSpecialization::class] as $field => $model) {
            if (! isset($data[$field])) {
                continue;
            }
            $item = $model::lockForUpdate()->find($data[$field]);
            $retained = ! $submit && (int) ($old[$field] ?? 0) === (int) $data[$field];
            if (! $item || (! $item->is_active && ! $retained)) {
                throw ValidationException::withMessages([$field => 'Danh mục không tồn tại hoặc đã ẩn. Vui lòng chọn lại.']);
            }
            if ($field === 'job_specialization_id' && $item->job_category_id !== (int) ($data['job_category_id'] ?? 0)) {
                throw ValidationException::withMessages([$field => 'Chuyên môn không thuộc nhóm nghề đã chọn.']);
            }
        }
        foreach (collect($data['skills'] ?? [])->sort()->all() as $id) {
            $skill = Skill::lockForUpdate()->find($id);
            $retained = ! $submit && in_array((int) $id, $old['skills'] ?? [], true);
            if (! $skill || (! $skill->is_active && ! $retained)) {
                throw ValidationException::withMessages(['skills' => 'Kỹ năng không tồn tại hoặc đã ẩn.']);
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
