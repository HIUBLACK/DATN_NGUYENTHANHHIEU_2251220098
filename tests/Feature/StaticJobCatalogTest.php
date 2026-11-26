<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Services\PublicJobService;
use App\Services\RecruiterJobService;
use App\Support\JobCatalog;
use Illuminate\Support\Facades\Schema;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;

class StaticJobCatalogTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;

    protected UserRole $demoRole = UserRole::Recruiter;

    public function test_catalog_tables_and_admin_crud_are_removed(): void
    {
        foreach (['industries', 'job_categories', 'job_specializations', 'skills', 'job_levels', 'job_positions'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->loginDemoUser(UserRole::Admin);
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Quản lý danh mục');
        foreach (['vi-tri', 'linh-vuc', 'nhom-nghe', 'chuyen-mon', 'ky-nang', 'cap-bac'] as $path) {
            $this->get('/admin/danh-muc/'.$path)->assertNotFound();
            $this->post('/admin/danh-muc/'.$path, ['name' => 'Không được tạo'])->assertNotFound();
        }
    }

    public function test_static_ids_and_specialization_parents_are_consistent(): void
    {
        foreach (array_keys(config('job-catalog')) as $group) {
            $items = JobCatalog::all($group);
            $names = $items->map(fn ($item) => mb_strtolower($item->name));
            $this->assertSame($items->count(), $names->unique()->count());
        }
        foreach (JobCatalog::all('specializations') as $item) {
            $this->assertNotNull($item->category);
            $this->assertSame($item->job_category_id, $item->category->id);
        }
        $this->assertSame('Git', JobCatalog::find('skills', 2)->name);
        $this->assertSame('Backend Developer', JobCatalog::find('specializations', 2)->name);
    }

    public function test_public_filters_use_static_names_and_published_version_only(): void
    {
        $this->companyFixture(auth()->id());
        $input = $this->completeInput();
        $job = app(RecruiterJobService::class)->create(auth()->user(), $input);
        $version = $this->publishFixture($job);
        $public = app(PublicJobService::class);
        $filters = [
            'industry' => JobCatalog::find('industries', $input['industry_id'])->name,
            'level' => 'Junior', 'skill' => 'PHP', 'occupationId' => 2,
            'specialtyId' => 2, 'location' => 'Đà Nẵng',
        ];
        $this->assertSame([$job->id], $public->search($filters)->pluck('id')->all());
        $this->assertFalse($public->search(array_replace($filters, ['skill' => 'Docker']))->exists());
        $this->assertFalse($public->search(array_replace($filters, ['occupationId' => 101]))->exists());
        $this->assertSame([$job->id], $public->search(['q' => 'PHP'])->pluck('id')->all());
        $this->get(route('jobs.show', $job->id))->assertOk()->assertSee('Backend Developer');
        $this->get('/viec-lam')->assertOk();
        $this->assertSame($version->id, $job->fresh()->published_version_id);
    }
}
