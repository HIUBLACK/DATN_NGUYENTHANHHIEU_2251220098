<?php

namespace App\Models;

use App\Enums\JobReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobVersion extends Model
{
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

    public function positionCatalog(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(JobSpecialization::class, 'job_specialization_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class, 'job_level_id');
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

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_version_skills', 'job_version_id', 'skill_id')
            ->using(JobVersionSkill::class)
            ->withPivot(['id', 'is_required'])
            ->withTimestamps();
    }
}
