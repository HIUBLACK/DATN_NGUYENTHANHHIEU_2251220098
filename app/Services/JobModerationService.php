<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\JobReviewStatus;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobModerationLog;
use App\Models\JobVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class JobModerationService
{
    public function __construct(private RecruiterJobService $jobs) {}

    public function decide(User $actor, int $versionId, string $action, array $data): void
    {
        abort_unless(in_array($action, ['approve', 'reject'], true), 404);
        DB::transaction(function () use ($actor, $versionId, $action, $data) {
            $reference = JobVersion::with('job.company')->findOrFail($versionId);
            // Khóa users theo id, rồi company/job/version; kiểm tra lại quan hệ sau khi khóa.
            $users = User::whereIn('id', [$actor->id, $reference->job->company->recruiter_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $company = Company::lockForUpdate()->findOrFail($reference->job->company_id);
            $job = Job::lockForUpdate()->findOrFail($reference->job_id);
            $version = JobVersion::where('job_id', $job->id)->lockForUpdate()->findOrFail($versionId);
            Gate::forUser($users->get($actor->id))->authorize('moderate', $job);
            if ($job->company_id !== $company->id || $company->recruiter_id !== $reference->job->company->recruiter_id) {
                throw ValidationException::withMessages(['moderation' => 'Chủ sở hữu đã thay đổi. Hãy tải lại trang.']);
            }
            if ($version->review_status !== JobReviewStatus::Pending
                || $version->getRawOriginal('updated_at') !== $data['updated_at']
                || $job->getRawOriginal('updated_at') !== $data['job_updated_at']) {
                throw ValidationException::withMessages(['moderation' => 'Tin đã thay đổi hoặc đã được xử lý. Hãy tải lại trang.']);
            }
            if ($action === 'approve') {
                $owner = $users->get($company->recruiter_id);
                if ($job->status === JobStatus::Blocked || $company->status !== CompanyStatus::Active
                    || $owner?->status !== UserStatus::Active || $owner?->role !== UserRole::Recruiter) {
                    throw ValidationException::withMessages(['moderation' => 'Không thể duyệt khi tin, doanh nghiệp hoặc nhà tuyển dụng bị khóa/không hợp lệ.']);
                }
                $this->jobs->validateForReview($version);
            } elseif (! preg_match('/[^\s\p{Z}\p{C}]/u', $data['reason'] ?? '') || mb_strlen($data['reason']) > 2000) {
                throw ValidationException::withMessages(['reason' => 'Nhập lý do từ chối, tối đa 2000 ký tự.']);
            }
            $version->review_status = $action === 'approve' ? JobReviewStatus::Approved : JobReviewStatus::Rejected;
            $version->reviewed_at = now();
            $version->save();
            $log = new JobModerationLog;
            $log->job_id = $job->id;
            $log->job_version_id = $version->id;
            $log->admin_id = $actor->id;
            $log->action = $action;
            $log->reason = $action === 'reject' ? trim($data['reason']) : null;
            $log->save();
            if ($action === 'approve') {
                $job->published_version_id = $version->id;
                $job->published_at = $version->reviewed_at;
                $job->expires_at = CarbonImmutable::parse($version->application_deadline->format('Y-m-d'), 'Asia/Ho_Chi_Minh')->addDay()->startOfDay()->utc();
                if ($job->status === JobStatus::Draft) {
                    $job->status = JobStatus::Open;
                }
                $job->save();
            }
        }, 3);
    }
}
