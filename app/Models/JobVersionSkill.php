<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class JobVersionSkill extends Pivot
{
    protected $table = 'job_version_skills';

    public $incrementing = true;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'job_version_id' => 'integer',
            'skill_id' => 'integer',
            'is_required' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function jobVersion(): BelongsTo
    {
        return $this->belongsTo(JobVersion::class, 'job_version_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
