<?php

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Job extends Model
{
    protected $table = 'jobs';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'status' => JobStatus::class,
            'status_before_block' => JobStatus::class,
            'published_version_id' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(JobVersion::class, 'job_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(JobVersion::class, 'job_id')->ofMany('version_number', 'max');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(JobVersion::class, 'published_version_id');
    }

    public function publishedVersionQuery(): Builder
    {
        return $this->publishedVersion()->getQuery()
            ->where('job_versions.job_id', $this->getKey());
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'job_id');
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(JobModerationLog::class, 'job_id');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
