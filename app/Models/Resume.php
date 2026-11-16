<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resume extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'resumes';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'display_name',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'candidate_id' => 'integer',
            'current_revision' => 'integer',
            'is_primary' => 'boolean',
            'deleted_at' => 'datetime',
            'primary_slot' => 'integer',
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

    public function revisions(): HasMany
    {
        return $this->hasMany(ResumeRevision::class, 'resume_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(ResumeSkill::class, 'resume_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'resume_id');
    }

    /** Query by both keys; call currentRevision()->first(), not with('currentRevision'). */
    public function currentRevision(): Builder
    {
        return $this->revisions()->getQuery()
            ->where('resume_revisions.revision', $this->current_revision);
    }

    public function currentSkillsQuery(): Builder
    {
        return $this->skills()->getQuery()
            ->where('resume_skills.resume_revision', $this->current_revision);
    }

    public function scopeForCandidate(Builder $query, int $candidateId): Builder
    {
        return $query->where('resumes.candidate_id', $candidateId);
    }
}
