<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobModerationLog extends Model
{
    public function jobVersionQuery(): Builder
    {
        return $this->jobVersion()->getQuery()->where('job_versions.job_id', $this->job_id);
    }

    protected $table = 'job_moderation_logs';

    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'job_id' => 'integer',
            'job_version_id' => 'integer',
            'admin_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function jobVersion(): BelongsTo
    {
        return $this->belongsTo(JobVersion::class, 'job_version_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
