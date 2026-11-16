<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationNote extends Model
{
    protected $table = 'application_notes';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'application_id' => 'integer',
            'recruiter_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }
}
