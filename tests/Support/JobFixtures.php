<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait JobFixtures
{
    protected function companyFixture(int $owner): Company
    {
        $company = new Company;
        $company->forceFill([
            'recruiter_id' => $owner, 'slug' => (string) Str::uuid(), 'name' => 'Job Test Company',
            'email' => 'private@test.invalid', 'phone' => '0912345678', 'address' => 'Test address',
            'office_images' => [], 'status' => 'active',
        ])->save();

        return $company;
    }

    protected function completeInput(): array
    {
        $ids = [];
        foreach (['industries', 'job_categories', 'job_levels', 'skills', 'job_specializations'] as $table) {
            $code = substr((string) Str::uuid(), 0, 18);
            $row = ['code' => $code, 'name' => $table.$code, 'normalized_name' => $code, 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()];
            if ($table !== 'skills') {
                $row['slug'] = $code;
            }
            if ($table === 'job_specializations') {
                $row['job_category_id'] = $ids['job_categories'];
            }
            $ids[$table] = DB::table($table)->insertGetId($row);
        }

        return [
            'title' => 'Published backend role', 'position' => 'Backend Developer',
            'description' => 'Public description', 'requirements' => 'Public requirements', 'benefits' => 'Public benefits',
            'salary_min' => '16000000', 'salary_max' => '20000000', 'location' => 'Đà Nẵng',
            'work_mode' => 'remote', 'employment_type' => 'full_time', 'quantity' => 2,
            'experience_summary' => '1–3 năm', 'application_deadline' => now('Asia/Ho_Chi_Minh')->addDays(7)->toDateString(),
            'industry_id' => $ids['industries'], 'job_category_id' => $ids['job_categories'],
            'job_level_id' => $ids['job_levels'], 'job_specialization_id' => $ids['job_specializations'], 'skills' => [$ids['skills']],
        ];
    }

    protected function publishFixture(Job $job): JobVersion
    {
        $version = $job->versions()->orderByDesc('version_number')->firstOrFail();
        DB::table('job_versions')->where('id', $version->id)->update(['review_status' => 'approved', 'submitted_at' => now(), 'reviewed_at' => now()]);
        DB::table('jobs')->where('id', $job->id)->update([
            'status' => 'open', 'published_version_id' => $version->id, 'published_at' => now(),
            'expires_at' => $version->application_deadline->copy()->setTimezone('Asia/Ho_Chi_Minh')->addDay()->startOfDay()->utc(),
        ]);
        $job->refresh();

        return $version->fresh();
    }
}
