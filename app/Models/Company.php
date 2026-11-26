<?php

namespace App\Models;

use App\Support\JobCatalog;

use App\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Company extends Model
{
    public function getFullAddressAttribute(): string
    {
        if ($this->country !== 'Việt Nam') {
            return $this->address ?? '';
        }

        return \App\Support\VietnamAddress::format($this->address, $this->province_code, $this->ward_code);
    }

    use HasUuids;

    protected $table = 'companies';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'name',
        'description',
        'work_environment',
        'company_size',
        'company_type',
        'country',
        'website',
        'email',
        'phone',
        'address',
        'working_hours',
        'overtime_policy',
    ];

    protected $hidden = [
        'logo_path',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'recruiter_id' => 'integer',
            'office_images' => 'array',
            'industry_id' => 'integer',
            'status' => CompanyStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function getIndustryAttribute(): ?\stdClass
    {
        return JobCatalog::find('industries', $this->industry_id);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'company_id');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
