<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobVersion;
use App\Support\JobInput;
use App\Support\JobCatalog;
use App\Support\Provinces;
use Illuminate\Database\Eloquent\Builder;

class PublicJobService
{
    public function query(): Builder
    {
        return Job::with(['company', 'publishedVersion.skillRequirements'])
            ->where('status', 'open')->where('expires_at', '>', now())
            ->whereHas('publishedVersion', function (Builder $query) {
                $query->whereColumn('job_versions.job_id', 'jobs.id')->where('review_status', 'approved')
                    ->whereDate('application_deadline', '>=', now('Asia/Ho_Chi_Minh')->toDateString());
            })->whereHas('company', function (Builder $query) {
                $query->where('status', 'active')->whereHas('recruiter', function (Builder $user) {
                    $user->where('status', 'active')->where('role', 'recruiter');
                });
            });
    }

    public function search(array $filters): Builder
    {
        $query = $this->query();
        if (! empty($filters['q'])) {
            $pattern = '%'.addcslashes($filters['q'], '%_\\').'%';
            $skillIds = JobCatalog::all('skills')->filter(function ($skill) use ($filters) {
                return mb_stripos($skill->name, $filters['q']) !== false;
            })->pluck('id')->all();
            $query->where(function (Builder $query) use ($pattern, $skillIds) {
                $query->whereHas('publishedVersion', function (Builder $version) use ($pattern, $skillIds) {
                    $version->where('title', 'like', $pattern)->orWhere('description', 'like', $pattern)
                        ->orWhereHas('skillRequirements', fn ($skill) => $skill->whereIn('skill_id', $skillIds));
                })->orWhereHas('company', fn ($company) => $company->where('name', 'like', $pattern));
            });
        }
        if (! empty($filters['company'])) {
            $query->whereHas('company', fn ($company) => $company->where('name', $filters['company']));
        }
        $query->whereHas('publishedVersion', function (Builder $version) use ($filters) {
            if (! empty($filters['location'])) {
                $provinceCode = array_search($filters['location'], Provinces::ALL, true);
                $version->where(function (Builder $location) use ($provinceCode, $filters) {
                    if ($provinceCode !== false) {
                        $location->where('province_code', (string) $provinceCode)->orWhere(function (Builder $legacy) use ($filters) {
                            $legacy->whereNull('province_code')->where('location', $filters['location']);
                        });
                    } else {
                        $location->where('location', $filters['location']);
                    }
                });
            }
            foreach (['experience' => 'experience_summary', 'specialtyId' => 'job_specialization_id'] as $input => $column) {
                if (! empty($filters[$input])) {
                    $version->where($column, $filters[$input]);
                }
            }
            foreach (['industry' => ['industries', 'industry_id'], 'level' => ['levels', 'job_level_id']] as $input => [$group, $column]) {
                if (! empty($filters[$input])) {
                    $ids = JobCatalog::all($group)->where('name', $filters[$input])->pluck('id')->all();
                    $version->whereIn($column, $ids);
                }
            }
            if (! empty($filters['skill'])) {
                $ids = JobCatalog::all('skills')->where('name', $filters['skill'])->pluck('id')->all();
                $version->whereHas('skillRequirements', fn ($item) => $item->whereIn('skill_id', $ids));
            }
            if (! empty($filters['occupationId'])) {
                $ids = JobCatalog::all('specializations')->where('job_category_id', $filters['occupationId'])->pluck('id')->all();
                $version->whereIn('job_specialization_id', $ids);
            }
            foreach (['mode' => ['work_mode', JobInput::MODES], 'type' => ['employment_type', JobInput::TYPES]] as $input => [$column, $labels]) {
                if (! empty($filters[$input])) {
                    $value = array_search($filters[$input], $labels, true);
                    $version->where($column, $value === false ? $filters[$input] : $value);
                }
            }
            if (! empty($filters['salary'])) {
                $version->where('salary_min', '>=', bcmul((string) $filters['salary'], '1000000', 2));
            }
        });
        if (($filters['sort'] ?? '') === 'salary') {
            $query->orderByDesc(JobVersion::select('salary_max')->whereColumn('id', 'jobs.published_version_id')->limit(1));
        }

        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function card(Job $job): array
    {
        $version = $job->publishedVersion;
        $company = app(CompanyService::class)->publicData($job->company);

        return [
            'real' => true, 'id' => $job->id, 'title' => $version->title,
            'company' => $job->company->name, 'company_url' => route('companies.show', $job->company->public_id),
            'logo_url' => $company['logo_url'], 'status' => 'open',
            'salary_min' => rtrim(rtrim(bcdiv($version->salary_min, '1000000', 2), '0'), '.'),
            'salary_max' => rtrim(rtrim(bcdiv($version->salary_max, '1000000', 2), '0'), '.'),
            'salary_negotiable' => $version->salary_negotiable,
            'location' => Provinces::ALL[$version->province_code] ?? $version->location,
            'location_detail' => $version->full_location, 'level' => $version->level?->name,
            'mode' => JobInput::MODES[$version->work_mode], 'type' => JobInput::TYPES[$version->employment_type],
            'skills' => $version->skills->pluck('name')->all(), 'deadline' => $version->application_deadline,
            'description' => $version->description, 'requirements' => $version->requirements, 'benefits' => $version->benefits,
            'experience' => $version->experience_summary, 'quantity' => $version->quantity,
            'position' => $version->position, 'specialization' => $version->specialization?->name,
        ];
    }

    public function navigation(): array
    {
        $groups = JobCatalog::all('categories')->map(fn ($item) => [
            'id' => (string) $item->id, 'name' => $item->name, 'status' => 'active',
        ])->all();
        $specialties = JobCatalog::all('specializations')->map(fn ($item) => [
                'id' => (string) $item->id, 'occupationId' => (string) $item->job_category_id, 'name' => $item->name, 'status' => 'active',
            ])->all();
        $categories = [];
        foreach (['industries', 'levels', 'skills'] as $key) {
            $categories[$key] = JobCatalog::all($key)->map(fn ($item) => [
                'id' => (string) $item->id, 'name' => $item->name, 'status' => 'active',
            ])->all();
        }
        $versions = JobVersion::whereIn('id', $this->query()->select('published_version_id'));
        $filters = [
            'location' => ['Địa điểm', array_values(Provinces::ALL)],
            'experience' => ['Kinh nghiệm', (clone $versions)->whereNotNull('experience_summary')->distinct()->pluck('experience_summary')->all()],
            'industry' => ['Lĩnh vực', array_column($categories['industries'], 'name')],
            'skill' => ['Kỹ năng', array_column($categories['skills'], 'name')],
            'level' => ['Cấp bậc', array_column($categories['levels'], 'name')],
            'mode' => ['Hình thức', array_values(JobInput::MODES)],
            'type' => ['Loại công việc', array_values(JobInput::TYPES)],
            'company' => ['Công ty', Company::whereIn('id', $this->query()->select('company_id'))->orderBy('name')->pluck('name')->all()],
        ];

        return ['jobCategories' => $categories, 'occupationOptions' => $groups, 'specialtyOptions' => $specialties, 'filterOptions' => $filters];
    }

    public function featuredCompanies(): array
    {
        return app(CompanyService::class)->publicQuery()
            ->whereIn('id', $this->query()->select('company_id'))
            ->withCount(['jobs as openings' => fn ($query) => $query->whereIn('jobs.id', $this->query()->select('id'))])
            ->orderByDesc('openings')->limit(6)->get()->map(function ($company) {
                $profile = app(CompanyService::class)->publicData($company);

                return ['name' => $company->name, 'logo_url' => $profile['logo_url'], 'locations' => $company->full_address, 'openings' => $company->openings];
            })->all();
    }
}
