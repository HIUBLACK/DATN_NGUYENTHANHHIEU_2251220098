<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends Model
{
    protected $table = 'skills';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'name',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function resumeSkills(): HasMany
    {
        return $this->hasMany(ResumeSkill::class, 'skill_id');
    }

    public function jobVersionSkills(): HasMany
    {
        return $this->hasMany(JobVersionSkill::class, 'skill_id');
    }

    public function jobVersions(): BelongsToMany
    {
        return $this->belongsToMany(JobVersion::class, 'job_version_skills', 'skill_id', 'job_version_id')
            ->using(JobVersionSkill::class)
            ->withPivot(['id', 'is_required'])
            ->withTimestamps();
    }
}
