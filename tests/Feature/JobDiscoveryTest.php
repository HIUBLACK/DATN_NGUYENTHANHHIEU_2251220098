<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Support\CandidateCatalog;
use App\Support\OccupationCatalog;
use App\Support\RecruiterCatalog;
use Tests\AuthenticatedDemoTestCase;

class JobDiscoveryTest extends AuthenticatedDemoTestCase
{
    protected UserRole $demoRole = UserRole::Admin;

    public function test_filters_combine_and_reject_mismatched_categories(): void
    {
        $catalog = app(CandidateCatalog::class);
        $filters = [
            'occupationId' => 'NN-001',
            'specialtyId' => 'CM-002',
            'location' => 'Đà Nẵng',
            'experience' => '1–3 năm',
            'skill' => 'Laravel',
            'mode' => 'Từ xa',
            'salary' => 30,
        ];
        $jobs = $catalog->search($filters);
        $this->assertCount(1, $jobs);
        $this->assertEquals(11, $jobs->first()['id']);
        $this->assertCount(0, $catalog->search(array_replace($filters, ['occupationId' => 'NN-002'])));
        $this->get(route('jobs', $filters))->assertOk()->assertDontSee('Backend Developer tại Đà Nẵng');
    }

    public function test_menu_filters_use_exact_company_and_industry(): void
    {
        $catalog = app(CandidateCatalog::class);
        $filters = [
            'industry' => 'Phát triển phần mềm',
            'company' => 'Nexa Technology',
            'skill' => 'PHP',
            'level' => 'Có kinh nghiệm',
            'location' => 'Đà Nẵng',
        ];
        $this->assertSame([11], $catalog->search($filters)->pluck('id')->all());
        $this->assertCount(0, $catalog->search(array_replace($filters, ['company' => 'Nexa'])));
        $this->assertCount(0, $catalog->search(array_replace($filters, ['industry' => 'Dữ liệu và AI'])));
        $this->get(route('jobs', $filters))->assertOk()->assertDontSee('Backend Developer tại Đà Nẵng');
        foreach (['linh-vuc', 'ky-nang', 'cap-bac'] as $path) {
            $this->get('/admin/danh-muc/'.$path)->assertOk()->assertSee('Thêm danh mục')->assertDontSee('admin-assets/js/admin-categories.js');
            $this->get('/admin/danh-muc/'.$path.'/tao')->assertOk()->assertSee('category-name');
        }
    }

    public function test_every_publication_condition_is_required(): void
    {
        $catalog = app(CandidateCatalog::class);
        $job = $catalog->find(1);
        $this->assertTrue($catalog->isPublic($job));
        foreach ([
            ['moderation' => 'pending'],
            ['moderation' => 'rejected'],
            ['status' => 'closed'],
            ['status' => 'paused'],
            ['status' => 'blocked'],
            ['blocked' => true],
            ['company_status' => 'blocked'],
            ['recruiter_status' => 'blocked'],
            ['deadline' => now()->subDay()],
        ] as $change) {
            $this->assertFalse($catalog->isPublic(array_replace($job, $change)));
        }
        $this->assertNotContains(9, $catalog->search([])->pluck('id')->all());
        $this->assertNotContains(10, $catalog->search([])->pluck('id')->all());
    }

    public function test_sorting_and_shared_category_relationships(): void
    {
        $catalog = app(CandidateCatalog::class);
        $this->assertEquals(1, $catalog->search(['sort' => 'newest'])->first()['id']);
        $this->assertEquals(11, $catalog->search(['sort' => 'salary'])->first()['id']);
        $specialties = collect(app(OccupationCatalog::class)->specialties())->keyBy('id');
        foreach ($catalog->jobs() as $job) {
            $this->assertSame($specialties[$job['specialtyId']]['occupationId'], $job['occupationId']);
        }
        foreach (app(RecruiterCatalog::class)->data()['jobs'] as $job) {
            $this->assertSame($specialties[$job['content']['specialtyId']]['occupationId'], $job['content']['occupationId']);
        }
        $this->get('/viec-lam?occupationId=NN-001&sort=salary')->assertOk();
    }
}
