<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeSkill extends Model
{
    public function scopeForRevision(Builder $query, int $resumeId, int $revision): Builder
    {
        return $query->where('resume_skills.resume_id', $resumeId)
            ->where('resume_skills.resume_revision', $revision);
    }

    public function revisionQuery(): Builder
    {
        return $this->newRelatedInstance(ResumeRevision::class)->newQuery()
            ->where('resume_revisions.resume_id', $this->resume_id)
            ->where('resume_revisions.revision', $this->resume_revision);
    }

    protected $table = 'resume_skills';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'resume_id' => 'integer',
            'resume_revision' => 'integer',
            'skill_id' => 'integer',
            'confidence_score' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class, 'resume_id')->withTrashed();
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
