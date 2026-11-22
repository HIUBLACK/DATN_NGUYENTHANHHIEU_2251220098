<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Support\AdminCatalog;
use Tests\AuthenticatedDemoTestCase;

class AdminPageTest extends AuthenticatedDemoTestCase
{
    protected UserRole $demoRole = UserRole::Admin;

    public function test_admin_pages_are_isolated_and_render_with_authenticated_admin(): void
    {
        $pages = ['dashboard', 'users.index', 'companies.index', 'jobs.index', 'jobs.show', 'jobs.history', 'applications.index', 'account'];
        foreach ($pages as $page) {
            $this->get(route('admin.'.$page))
                ->assertOk()
                ->assertViewIs('Admin.ad_pages.'.$page)
                ->assertSee('admin-assets/css/admin.css')
                ->assertSee('admin-seed')
                ->assertDontSee('recruiter/css/recruiter.css')
                ->assertDontSee('jobboard/css/custom-bs.css');
        }
        foreach (['users.show', 'users.history', 'companies.show', 'applications.show'] as $page) {
            $this->get(route('admin.'.$page))->assertNotFound();
        }
        $this->get('/admin/ky-nang')->assertNotFound();
        $this->get('/admin/ky-nang/ten-dong-nghia')->assertNotFound();
        $this->get('/nha-tuyen-dung')->assertForbidden();
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
        $this->assertNotEquals($seed['jobs'][0]['content'], $seed['jobs'][0]['published']);
    }
}
