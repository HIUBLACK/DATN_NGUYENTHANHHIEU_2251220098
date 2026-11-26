<?php

namespace App\Models;

use App\Support\JobCatalog;

use App\Enums\JobReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobVersion extends Model
{
    public function getFullLocationAttribute(): string
    {
        return \App\Support\VietnamAddress::format($this->location, $this->province_code, $this->ward_code);
    }

    public function scopeForJob(Builder $query, int $jobId): Builder
    {
        return $query->where('job_versions.job_id', $jobId);
    }

    protected $table = 'job_versions';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'title',
        'position',
        'province_code',
        'ward_code',
        'description',
        'requirements',
        'benefits',
        'salary_min',
        'salary_max',
        'salary_currency',
        'salary_negotiable',
        'location',
        'work_mode',
        'employment_type',
        'quantity',
        'experience_summary',
        'experience_min',
        'experience_max',
        'application_deadline',
    ];

    protected function casts(): array
    {
        return [
            'ai_analysis_result' => 'array',
            'ai_attempt_count' => 'integer',
            'ai_started_at' => 'datetime',
            'ai_lease_expires_at' => 'datetime',
            'ai_next_retry_at' => 'datetime',
            'ai_analyzed_at' => 'datetime',
            'id' => 'integer',
            'job_id' => 'integer',
            'version_number' => 'integer',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'salary_negotiable' => 'boolean',
            'quantity' => 'integer',
            'experience_min' => 'decimal:1',
            'experience_max' => 'decimal:1',
            'industry_id' => 'integer',
            'job_position_id' => 'integer',
            'job_specialization_id' => 'integer',
            'job_level_id' => 'integer',
            'application_deadline' => 'date',
            'review_status' => JobReviewStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'pending_slot' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function getPositionCatalogAttribute(): ?\stdClass
    {
        return JobCatalog::find('positions', $this->job_position_id);
    }

    public function getIndustryAttribute(): ?\stdClass
    {
        return JobCatalog::find('industries', $this->industry_id);
    }

    public function getSpecializationAttribute(): ?\stdClass
    {
        return JobCatalog::find('specializations', $this->job_specialization_id);
    }

    public function getLevelAttribute(): ?\stdClass
    {
        return JobCatalog::find('levels', $this->job_level_id);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'job_version_id');
    }

    public function skillRequirements(): HasMany
    {
        return $this->hasMany(JobVersionSkill::class, 'job_version_id');
    }

    public function moderationLog(): HasOne
    {
        return $this->hasOne(JobModerationLog::class, 'job_version_id');
    }

    /** Giữ pivot nghiệp vụ; tên kỹ năng lấy từ danh sách cố định. */
    public function getSkillsAttribute(): \Illuminate\Support\Collection
    {
        return $this->skillRequirements->map(function ($requirement) {
            $skill = JobCatalog::find('skills', $requirement->skill_id);
            if ($skill) {
                $skill->pivot = $requirement;
            }

            return $skill;
        })->filter()->values();
    }
}
