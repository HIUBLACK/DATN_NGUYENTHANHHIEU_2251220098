<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobLevel extends Model
{
    protected $table = 'job_levels';

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

    public function jobVersions(): HasMany
    {
        return $this->hasMany(JobVersion::class, 'job_level_id');
    }
}
