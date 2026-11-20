<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobSpecialization;
use App\Models\JobVersion;
use App\Models\Skill;
use App\Support\JobInput;
use App\Support\Provinces;
use Illuminate\Database\Eloquent\Builder;

class PublicJobService
{
    public function query(): Builder
    {
        return Job::with(['company.industry', 'publishedVersion.skills', 'publishedVersion.level', 'publishedVersion.specialization'])
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
            $query->where(function (Builder $query) use ($pattern) {
                $query->whereHas('publishedVersion', function (Builder $version) use ($pattern) {
                    $version->where('title', 'like', $pattern)->orWhere('description', 'like', $pattern)
                        ->orWhereHas('skills', fn ($skill) => $skill->where('name', 'like', $pattern));
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
            foreach (['industry' => 'industry', 'level' => 'level', 'skill' => 'skills'] as $input => $relation) {
                if (! empty($filters[$input])) {
                    $version->whereHas($relation, fn ($item) => $item->where('name', $filters[$input]));
                }
            }
            if (! empty($filters['occupationId'])) {
                $version->whereHas('specialization', fn ($item) => $item->where('job_category_id', $filters['occupationId']));
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
            'location_detail' => $version->location, 'level' => $version->level?->name,
            'mode' => JobInput::MODES[$version->work_mode], 'type' => JobInput::TYPES[$version->employment_type],
            'skills' => $version->skills->pluck('name')->all(), 'deadline' => $version->application_deadline,
            'description' => $version->description, 'requirements' => $version->requirements, 'benefits' => $version->benefits,
            'experience' => $version->experience_summary, 'quantity' => $version->quantity,
            'position' => $version->position, 'specialization' => $version->specialization?->name,
        ];
    }

    public function navigation(): array
    {
        $groups = JobCategory::where('is_active', true)->orderBy('name')->get()->map(fn ($item) => [
            'id' => (string) $item->id, 'name' => $item->name, 'status' => 'active',
        ])->all();
        $specialties = JobSpecialization::where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')->get()->map(fn ($item) => [
                'id' => (string) $item->id, 'occupationId' => (string) $item->job_category_id, 'name' => $item->name, 'status' => 'active',
            ])->all();
        $categories = [];
        foreach (['industries' => Industry::class, 'levels' => JobLevel::class, 'skills' => Skill::class] as $key => $model) {
            $categories[$key] = $model::where('is_active', true)->orderBy('name')->get()->map(fn ($item) => [
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

                return ['name' => $company->name, 'logo_url' => $profile['logo_url'], 'locations' => $company->address, 'openings' => $company->openings];
            })->all();
    }
}
