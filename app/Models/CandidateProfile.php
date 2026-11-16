<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateProfile extends Model
{
    protected $table = 'candidate_profiles';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'phone',
        'location',
        'professional_title',
        'experience_summary',
        'summary',
    ];

    protected $hidden = [
        'avatar_path',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'recommendation_generation' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
