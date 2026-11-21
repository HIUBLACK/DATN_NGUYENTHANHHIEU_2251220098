<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Support\RecruiterCatalog;
use Tests\AuthenticatedDemoTestCase;

class RecruiterPageTest extends AuthenticatedDemoTestCase
{
    protected UserRole $demoRole = UserRole::Recruiter;

    public function test_recruiter_pages_render_with_separate_template_and_demo_data(): void
    {
        $names = ['dashboard', 'company', 'company-create', 'jobs', 'applications', 'notifications', 'account'];
        foreach ($names as $name) {
            $this->get(route('recruiter.'.$name))
                ->assertOk()
                ->assertSee('recruiter/css/main.css')
                ->assertSee('recruiter-seed')
                ->assertDontSee('jobboard/css/custom-bs.css');
        }
        $this->get(route('recruiter.application-detail'))->assertNotFound();
        $this->get(route('recruiter.company-edit'))->assertRedirect(route('recruiter.company-create'));
        $this->get(route('recruiter.job-create'))->assertForbidden();
        $this->get('/nha-tuyen-dung')->assertOk()->assertViewIs('employer.dashboard');
        $this->get('/viec-lam')->assertOk()->assertDontSee('recruiter/css/main.css');
    }

    public function test_only_synthetic_application_has_its_own_demo_pdf(): void
    {
        $data = app(RecruiterCatalog::class)->data();
        $this->assertNotNull($data['applications'][0]['cv']);
        foreach (array_slice($data['applications'], 1) as $application) {
            $this->assertNull($application['cv']);
        }
        $pdf = file_get_contents(public_path('recruiter/demo/cv-demo-01.pdf'));
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('SYNTHETIC CV DEMO 01', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }
}
