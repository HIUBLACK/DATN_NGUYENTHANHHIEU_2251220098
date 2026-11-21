<?php

namespace Tests\Feature;

use Tests\AuthenticatedDemoTestCase;

class CandidatePreviewTest extends AuthenticatedDemoTestCase
{
    public function test_demo_recommendations_remain_separate_from_real_jobs(): void
    {
        $this->get(route('candidate.recommendations'))->assertOk()
            ->assertViewHas('recommendations', fn ($jobs) => count($jobs) === 0);
        $this->get(route('jobs'))->assertOk()->assertDontSee('phù hợp · Minh họa');
    }

    public function test_candidate_pages_render_with_authenticated_candidate(): void
    {
        $paths = ['/', '/viec-lam', '/viec-lam/mau/1', '/cong-ty', '/gioi-thieu'];
        foreach (['cv', 'goi-y-ai', 'da-luu', 'ung-tuyen', 'thong-bao', 'tin-nhan', 'ho-so', 'cai-dat'] as $page) {
            $paths[] = '/ung-vien/'.$page;
        }
        foreach ($paths as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/viec-lam/999')->assertNotFound();
        $this->get('/viec-lam?q=nonexistent')->assertOk()->assertSee('Không tìm thấy');
    }
}
