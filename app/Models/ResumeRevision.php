<?php

namespace App\Models;

use App\Enums\ResumeParsingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeRevision extends Model
{
    protected $table = 'resume_revisions';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected $hidden = [
        'file_path',
        'extracted_text_path',
        'error_message',
        'processing_token',
    ];

    protected function casts(): array
    {
        return [
            'analysis_result' => 'array',
            'id' => 'integer',
            'resume_id' => 'integer',
            'revision' => 'integer',
            'file_size' => 'integer',
            'parsing_status' => ResumeParsingStatus::class,
            'parsed_at' => 'datetime',
            'attempt_count' => 'integer',
            'started_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class, 'resume_id')->withTrashed();
    }

    public function scopeForRevision(Builder $query, int $resumeId, int $revision): Builder
    {
        return $query->where('resume_revisions.resume_id', $resumeId)
            ->where('resume_revisions.revision', $revision);
    }

    /**
     * Composite-key query, not an eager-loadable relationship.
     * Use skills()->get(); with('skills') is intentionally unsupported.
     */
    public function skills(): Builder
    {
        return $this->newRelatedInstance(ResumeSkill::class)->newQuery()
            ->where('resume_skills.resume_id', $this->resume_id)
            ->where('resume_skills.resume_revision', $this->revision);
    }
}
