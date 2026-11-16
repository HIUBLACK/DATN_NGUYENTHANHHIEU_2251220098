<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationItem extends Model
{
    public function jobVersionQuery(): Builder
    {
        return $this->jobVersion()->getQuery()->where('job_versions.job_id', $this->job_id);
    }

    protected $table = 'recommendation_items';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'recommendation_run_id' => 'integer',
            'job_id' => 'integer',
            'job_version_id' => 'integer',
            'score' => 'decimal:2',
            'semantic_score' => 'decimal:2',
            'skill_score' => 'decimal:2',
            'matched_skills' => 'array',
            'missing_skills' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(RecommendationRun::class, 'recommendation_run_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function jobVersion(): BelongsTo
    {
        return $this->belongsTo(JobVersion::class, 'job_version_id');
    }
}
