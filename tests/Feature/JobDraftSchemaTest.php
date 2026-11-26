<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\JobFixtures;
use Tests\TestCase;

class JobDraftSchemaTest extends TestCase
{
    use JobFixtures;

    public function test_nullable_constraints_and_rollback_rehearsal_preserve_data_and_keys(): void
    {
        $database = DB::connection()->getDatabaseName();
        if (! preg_match('/^viejob_models_test_[a-f0-9]{12}$/', $database)) {
            $this->markTestSkipped('Requires isolated MariaDB test runner.');
        }
        $user = new User;
        $user->forceFill(['name' => 'Schema fixture', 'email' => Str::uuid().'@test.invalid', 'password' => 'Password123', 'role' => 'recruiter', 'status' => 'active'])->save();
        $company = $this->companyFixture($user->id);
        $job = new Job;
        $job->forceFill(['company_id' => $company->id, 'status' => 'draft'])->save();
        $input = $this->completeInput();
        $migration = require database_path('migrations/2026_11_17_100000_allow_incomplete_job_drafts.php');
        $base = ['job_id' => $job->id, 'version_number' => 1, 'title' => 'Title only', 'created_at' => now(), 'updated_at' => now()];
        try {
            $id = DB::table('job_versions')->insertGetId($base);
            $this->assertNull(DB::table('job_versions')->find($id)->job_specialization_id);
            try {
                $migration->down();
                $this->fail('Rollback must reject NULL draft fields.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('incomplete job drafts', $exception->getMessage());
            }
            $this->assertSame('Title only', DB::table('job_versions')->find($id)->title);
            foreach ([
                ['title' => '   '], ['salary_min' => -1], ['salary_max' => -1],
                ['salary_min' => 10, 'salary_max' => 9], ['quantity' => 0], ['quantity' => 1001],
                ['work_mode' => 'invalid'], ['employment_type' => 'contract'],
                ['review_status' => 'pending', 'submitted_at' => now()],
                ['review_status' => 'approved', 'submitted_at' => now(), 'reviewed_at' => now()],
                ['review_status' => 'rejected', 'submitted_at' => now(), 'reviewed_at' => now()],
            ] as $invalid) {
                try {
                    DB::table('job_versions')->where('id', $id)->update($invalid);
                    $this->fail('Invalid schema data was accepted: '.implode(',', array_keys($invalid)));
                } catch (QueryException) {
                    $this->assertSame('Title only', DB::table('job_versions')->find($id)->title);
                }
            }
            DB::table('job_versions')->where('id', $id)->update(Arr::except($input, ['job_category_id', 'skills']));
            $row = (array) DB::table('job_versions')->find($id);
            $beforeKeys = $this->keys();
            $this->assertEquals($row, (array) DB::table('job_versions')->find($id));
            $this->assertEquals($beforeKeys, $this->keys());
            $this->assertCount(27, DB::select('SHOW TABLES'));
            DB::table('job_versions')->where('id', $id)->update(['review_status' => 'pending', 'submitted_at' => now()]);
            $pending = Arr::except((array) DB::table('job_versions')->find($id), ['id', 'pending_slot']);
            $pending['version_number'] = 2;
            try {
                DB::table('job_versions')->insert($pending);
                $this->fail('Two pending versions must be rejected.');
            } catch (QueryException) {
                $this->assertSame(1, DB::table('job_versions')->where('job_id', $job->id)->count());
            }
        } finally {
            DB::table('job_versions')->where('job_id', $job->id)->delete();
            $job->delete();
            $company->delete();
            $user->delete();
        }
    }

    private function keys(): array
    {
        return DB::select("SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('jobs', 'job_versions') ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION");
    }
}
