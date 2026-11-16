<?php

namespace App\Models;

use App\Enums\AiEvaluationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAiEvaluation extends Model
{
    public function applicationQuery(): Builder
    {
        return $this->application()->getQuery()->where('applications.job_id', $this->job_id);
    }

    public function jobVersionQuery(): Builder
    {
        return $this->jobVersion()->getQuery()->where('job_versions.job_id', $this->job_id);
    }

    protected $table = 'application_ai_evaluations';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected $hidden = [
        'error_message',
        'processing_token',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'application_id' => 'integer',
            'job_id' => 'integer',
            'job_version_id' => 'integer',
            'score' => 'decimal:2',
            'semantic_score' => 'decimal:2',
            'skill_score' => 'decimal:2',
            'matched_skills' => 'array',
            'missing_skills' => 'array',
            'evaluation_status' => AiEvaluationStatus::class,
            'evaluated_at' => 'datetime',
            'attempt_count' => 'integer',
            'started_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
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
