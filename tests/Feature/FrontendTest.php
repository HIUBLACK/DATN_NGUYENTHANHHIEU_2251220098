<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendTest extends TestCase
{
    public function test_all_frontend_routes_render_without_database_mutations(): void
    {
        foreach (['/', '/viec-lam', '/viec-lam/1', '/cong-ty', '/gioi-thieu', '/nha-tuyen-dung', '/dang-nhap', '/dang-ky', '/quen-mat-khau', '/dat-lai-mat-khau', '/ung-vien/tong-quan', '/ung-vien/cv', '/ung-vien/ky-nang', '/ung-vien/goi-y-ai', '/ung-vien/da-luu', '/ung-vien/ung-tuyen', '/ung-vien/thong-bao', '/ung-vien/ho-so', '/ung-vien/cai-dat'] as $path) {
            $this->get($path)->assertOk()->assertSee('lang="vi"', false)->assertSee('Dữ liệu minh họa');
        }
    }

    public function test_filters_and_pagination_preserve_search(): void
    {
        $this->get('/viec-lam?q=Laravel')->assertOk()->assertSeeText('Lập trình viên Backend Laravel')->assertDontSee('data-job-card="2"', false);
        $this->get('/viec-lam?mode='.urlencode('Từ xa').'&salary=50')->assertOk()->assertSeeText('Kỹ sư DevOps')->assertDontSee('data-job-card="1"', false);
        $this->get('/viec-lam?q=khongcoketqua')->assertOk()->assertSee('Chưa tìm thấy công việc phù hợp');
        $this->get('/viec-lam?page=2')->assertOk()->assertSeeText('Lập trình viên ứng dụng di động')->assertDontSee('data-job-card="1"', false);
    }

    public function test_closed_expired_and_hidden_jobs_cannot_be_applied_to(): void
    {
        $this->get('/viec-lam/9')->assertOk()->assertSee('Không nhận ứng tuyển')->assertDontSee('data-apply="9"', false);
        $this->get('/viec-lam/10')->assertOk()->assertSee('Không nhận ứng tuyển')->assertDontSee('data-apply="10"', false);
        $this->get('/viec-lam/11')->assertNotFound()->assertSee('Nội dung không khả dụng')->assertDontSee('Pixel Studio');
        $this->get('/viec-lam/999')->assertNotFound();
        $this->get('/viec-lam')->assertDontSee('Tin không khả dụng');
    }
}
