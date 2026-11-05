<?php

namespace Tests\Feature;

use App\Support\AdminCatalog;
use Tests\TestCase;

class AdminPageTest extends TestCase
{
    public function test_admin_pages_are_isolated_and_render_without_database(): void
    {
        $pages = ['dashboard', 'users.index', 'users.show', 'users.history', 'companies.index', 'companies.show', 'jobs.index', 'jobs.queue', 'jobs.show', 'jobs.history', 'applications.index', 'applications.show', 'skills.index', 'skills.aliases', 'account'];
        foreach ($pages as $page) {
            $this->get(route('admin.'.$page))
                ->assertOk()
                ->assertViewIs('Admin.ad_pages.'.$page)
                ->assertSee('admin-assets/css/admin.css')
                ->assertSee('admin-seed')
                ->assertDontSee('recruiter/css/recruiter.css')
                ->assertDontSee('jobboard/css/custom-bs.css');
        }
        $this->get('/nha-tuyen-dung')->assertOk()->assertDontSee('admin-assets/css/admin.css');
        $this->get('/viec-lam')->assertOk()->assertDontSee('admin-assets/css/admin.css');
    }

    public function test_admin_seed_relationships_are_consistent_and_have_no_cv_content(): void
    {
        $seed = app(AdminCatalog::class)->data();
        foreach ($seed['jobs'] as $job) {
            $this->assertContains($job['companyId'], array_column($seed['companies'], 'id'));
        }
        foreach ($seed['applications'] as $application) {
            $this->assertContains($application['userId'], array_column($seed['users'], 'id'));
            $this->assertContains($application['jobId'], array_column($seed['jobs'], 'id'));
            $this->assertArrayNotHasKey('cv', $application);
        }
        foreach ($seed['aliases'] as $alias) {
            $this->assertContains($alias['skillId'], array_column($seed['skills'], 'id'));
        }
        $this->assertNotEquals($seed['jobs'][0]['content'], $seed['jobs'][0]['published']);
    }
}
