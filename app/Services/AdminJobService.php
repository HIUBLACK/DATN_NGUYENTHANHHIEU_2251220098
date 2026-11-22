<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobVersion;
use App\Models\User;
use App\Support\JobInput;
use App\Support\Provinces;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminJobService
{
    public function listing(array $filters)
    {
        $query = Job::with(['company.recruiter', 'latestVersion', 'publishedVersion'])->withMax('activityLogs', 'id');
        if (! empty($filters['q'])) {
            $pattern = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function (Builder $query) use ($pattern) {
                $query->whereHas('latestVersion', fn ($version) => $version->where('title', 'like', $pattern))
                    ->orWhereHas('publishedVersion', fn ($version) => $version->where('title', 'like', $pattern))
                    ->orWhereHas('company', fn ($company) => $company->where('name', 'like', $pattern));
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['review'])) {
            $query->whereHas('latestVersion', fn ($version) => $version->where('review_status', $filters['review']));
        }

        return $query->latest('id')->paginate(15)->withQueryString();
    }

    public function detail(int $id, ?int $versionId): array
    {
        $relations = ['skills', 'specialization.category', 'industry', 'level', 'positionCatalog'];
        $job = Job::with(['company.recruiter', 'publishedVersion'])->withCount('applications')->withMax('activityLogs', 'id')->findOrFail($id);
        Gate::authorize('moderate', $job);
        $versions = $job->versions()->with('moderationLog.admin')->latest('version_number')->paginate(15, ['*'], 'versions_page')->withQueryString();
        $selected = $job->versions()->with($relations)->when($versionId, fn ($query) => $query->whereKey($versionId))->latest('version_number')->first();
        abort_if($versionId && ! $selected, 404);
        $published = $job->publishedVersion?->load($relations);

        return [
            'job' => $job, 'versions' => $versions, 'selected' => $selected, 'published' => $published,
            'selectedContent' => $this->content($selected), 'publishedContent' => $this->content($published),
            'isPublic' => app(PublicJobService::class)->query()->whereKey($job->id)->exists(),
            'audit' => $job->activityLogs()->with('actor')->latest('id')->paginate(15, ['*'], 'audit_page')->withQueryString(),
            'moderation' => $job->moderationLogs()->with(['admin', 'jobVersion'])->latest('id')->paginate(15, ['*'], 'moderation_page')->withQueryString(),
        ];
    }

    private function content(?JobVersion $version): array
    {
        if (! $version) {
            return [];
        }

        return [
            'Tiêu đề' => $version->title,
            'Vị trí công việc' => $version->positionCatalog?->name ?? $version->position,
            'Chức danh' => $version->position,
            'Lĩnh vực' => $version->industry?->name,
            'Chuyên môn' => $version->specialization?->name,
            'Cấp bậc' => $version->level?->name,
            'Kỹ năng' => $version->skills->pluck('name')->sort()->implode(', '),
            'Lương tối thiểu' => $version->salary_min !== null ? number_format((float) $version->salary_min, 0, ',', '.').' '.$version->salary_currency : null,
            'Lương tối đa' => $version->salary_max !== null ? number_format((float) $version->salary_max, 0, ',', '.').' '.$version->salary_currency : null,
            'Thương lượng lương' => $version->salary_negotiable ? 'Có' : 'Không',
            'Tỉnh/thành phố' => Provinces::ALL[$version->province_code] ?? null,
            'Địa điểm chi tiết' => $version->location,
            'Hình thức làm việc' => JobInput::MODES[$version->work_mode] ?? null,
            'Loại công việc' => JobInput::TYPES[$version->employment_type] ?? null,
            'Số lượng' => $version->quantity,
            'Hạn ứng tuyển' => $version->application_deadline?->format('d/m/Y'),
            'Mô tả' => $version->description,
            'Yêu cầu' => $version->requirements,
            'Quyền lợi' => $version->benefits,
        ];
    }

    public function changeStatus(User $admin, int $id, array $input): void
    {
        DB::transaction(function () use ($admin, $id, $input) {
            $reference = Job::with('company')->findOrFail($id);
            $ownerId = $reference->company->recruiter_id;
            $users = User::whereIn('id', [$admin->id, $ownerId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $company = Company::lockForUpdate()->findOrFail($reference->company_id);
            $job = Job::lockForUpdate()->findOrFail($id);
            $actor = $users->get($admin->id);
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('moderate', $job);
            if ($job->company_id !== $company->id || $company->recruiter_id !== $ownerId
                || $job->status->value !== $input['expected_status']
                || $job->getRawOriginal('updated_at') !== $input['updated_at']
                || (int) $job->activityLogs()->max('id') !== (int) $input['expected_log_id']) {
                throw ValidationException::withMessages(['job' => 'Tin đã thay đổi. Vui lòng tải lại trang trước khi thao tác.']);
            }
            $blocking = $input['status'] === 'blocked';
            if (! in_array($input['status'], ['blocked', 'restore'], true)
                || $blocking === ($job->status === JobStatus::Blocked)) {
                throw ValidationException::withMessages(['job' => 'Thao tác không phù hợp với trạng thái hiện tại hoặc đã được thực hiện.']);
            }
            if (! preg_match('/[^\s\p{Z}\p{C}]/u', $input['reason'] ?? '') || mb_strlen($input['reason']) > 2000) {
                throw ValidationException::withMessages(['reason' => 'Vui lòng nhập lý do, tối đa 2000 ký tự.']);
            }
            $old = $this->snapshot($job);
            if ($blocking) {
                $job->status_before_block = $job->status;
                $job->status = JobStatus::Blocked;
            } else {
                $previous = $job->status_before_block;
                if (! in_array($previous, [JobStatus::Draft, JobStatus::Open, JobStatus::Paused, JobStatus::Closed], true)) {
                    throw ValidationException::withMessages(['job' => 'Không xác định được trạng thái trước khi chặn. Chưa thể mở chặn an toàn.']);
                }
                $job->status = $previous;
                if ($previous === JobStatus::Open) {
                    $company->setRelation('recruiter', $users->get($ownerId));
                    $job->setRelation('company', $company);
                    $job->setRelation('publishedVersion', $job->publishedVersionQuery()->lockForUpdate()->first());
                    // Chỉ khôi phục open khi chính bản đã công khai còn đủ điều kiện; không dùng bản mới nhất.
                    if (! Gate::forUser($actor)->allows('viewPublic', $job)) {
                        $job->status = JobStatus::Paused;
                    }
                }
                $job->status_before_block = null;
            }
            $job->save();
            $log = new ActivityLog;
            $log->forceFill([
                'actor_id' => $actor->id, 'subject_type' => 'job', 'subject_id' => $job->id,
                'action' => $blocking ? 'job.block' : 'job.unblock',
                'old_values' => $old, 'new_values' => $this->snapshot($job), 'reason' => trim($input['reason']),
            ])->save();
        }, 3);
    }

    private function snapshot(Job $job): array
    {
        return ['status' => $job->status->value, 'status_before_block' => $job->status_before_block?->value];
    }
}
