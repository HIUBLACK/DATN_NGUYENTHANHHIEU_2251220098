<?php

namespace App\Models;

use App\Enums\RecommendationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecommendationRun extends Model
{
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

    protected $table = 'recommendation_runs';

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
            'candidate_id' => 'integer',
            'resume_id' => 'integer',
            'resume_revision' => 'integer',
            'recommendation_generation' => 'integer',
            'status' => RecommendationStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'stale_at' => 'datetime',
            'attempt_count' => 'integer',
            'lease_expires_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class, 'resume_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecommendationItem::class, 'recommendation_run_id');
    }
}
