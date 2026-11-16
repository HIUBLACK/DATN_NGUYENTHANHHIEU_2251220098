<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    public function scopeForJob(Builder $query, int $jobId): Builder
    {
        return $query->where('applications.job_id', $jobId);
    }

    public function resumeRevisionQuery(): Builder
    {
        return $this->newRelatedInstance(ResumeRevision::class)->newQuery()
            ->where('resume_revisions.resume_id', $this->resume_id)
            ->where('resume_revisions.revision', $this->resume_revision);
    }

    public function resumeForCandidateQuery(): Builder
    {
        return $this->resume()->getQuery()->where('resumes.candidate_id', $this->candidate_id);
    }

    public function jobVersionQuery(): Builder
    {
        return $this->jobVersion()->getQuery()->where('job_versions.job_id', $this->job_id);
    }

    use HasUuids;

    protected $table = 'applications';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected $hidden = [
        'resume_snapshot_path',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'candidate_id' => 'integer',
            'job_id' => 'integer',
            'attempt_no' => 'integer',
            'resume_id' => 'integer',
            'resume_revision' => 'integer',
            'resume_snapshot_size' => 'integer',
            'job_version_id' => 'integer',
            'status' => ApplicationStatus::class,
            'applied_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function jobVersion(): BelongsTo
    {
        return $this->belongsTo(JobVersion::class, 'job_version_id');
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class, 'resume_id')->withTrashed();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'application_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class, 'application_id');
    }

    public function aiEvaluations(): HasMany
    {
        return $this->hasMany(ApplicationAiEvaluation::class, 'application_id');
    }
}
