<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Company;
use App\Models\User;

class AdminManagementService
{
    public function listing(string $section, array $filters)
    {
        $query = match ($section) {
            'users' => User::whereIn('role', ['candidate', 'recruiter'])->withMax('activityLogs', 'id'),
            'companies' => Company::with('recruiter')->withCount('jobs')->withMax('activityLogs', 'id'),
            'applications' => Application::with(['candidate', 'job.company.recruiter', 'jobVersion']),
        };
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($section === 'users' && ! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (! empty($filters['q'])) {
            $pattern = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($query) use ($section, $pattern) {
                if ($section === 'applications') {
                    $query->whereHas('candidate', fn ($user) => $user->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern))
                        ->orWhereHas('jobVersion', fn ($version) => $version->where('title', 'like', $pattern))
                        ->orWhereHas('job.company', fn ($company) => $company->where('name', 'like', $pattern));
                } else {
                    $query->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern);
                }
            });
        }
        if ($section === 'applications') {
            foreach (['candidate' => ['candidate', ['name', 'email']], 'company' => ['job.company', ['name']], 'recruiter' => ['job.company.recruiter', ['name', 'email']], 'job' => ['jobVersion', ['title']]] as $key => [$relation, $columns]) {
                if (! empty($filters[$key])) {
                    $pattern = '%'.addcslashes($filters[$key], '%_\\').'%';
                    $query->whereHas($relation, function ($related) use ($columns, $pattern) {
                        $related->where(function ($fields) use ($columns, $pattern) {
                            foreach ($columns as $column) {
                                $fields->orWhere($column, 'like', $pattern);
                            }
                        });
                    });
                }
            }
        }

        return $query->latest('id')->paginate(15)->withQueryString();
    }
}
