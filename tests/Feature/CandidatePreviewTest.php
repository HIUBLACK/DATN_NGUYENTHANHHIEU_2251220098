<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidatePreviewTest extends TestCase
{
    public function test_jobs_discovery_requires_confirmed_cv_for_suggestions(): void
    {
        Storage::fake('local');
        $this->get('/viec-lam')
            ->assertSee('Các công ty nổi bật')
            ->assertSee('Hoàn thiện CV để xem công việc gợi ý');

        $resume = $this->uploadResume();
        $this->post('/ung-vien/cv/'.$resume.'/skills', ['skills' => 'PHP, Laravel, MySQL'])
            ->assertSessionHasNoErrors();

        $this->get('/viec-lam')
            ->assertOk()
            ->assertViewHas('suggestedJobs', fn ($jobs) => count($jobs) === 3)
            ->assertDontSee('Hoàn thiện CV để xem công việc gợi ý')
            ->assertSee('phù hợp · Minh họa');
    }

    private function uploadResume(): string
    {
        $file = UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->post('/ung-vien/cv', ['pdf' => $file])->assertSessionHasNoErrors();

        return collect(session('candidate_preview.resumes'))->last()['id'];
    }

    public function test_all_pages_render_without_database(): void
    {
        $paths = ['/', '/viec-lam', '/viec-lam/1', '/cong-ty', '/gioi-thieu', '/nha-tuyen-dung', '/dang-nhap', '/dang-ky', '/quen-mat-khau', '/dat-lai-mat-khau'];
        foreach (['cv', 'ky-nang', 'goi-y-ai', 'da-luu', 'ung-tuyen', 'thong-bao', 'tin-nhan', 'ho-so', 'cai-dat'] as $page) {
            $paths[] = '/ung-vien/'.$page;
        }
        foreach ($paths as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/viec-lam/999')->assertNotFound();
        $this->get('/viec-lam?q=nonexistent')->assertOk()->assertSee('Không tìm thấy');
    }

    public function test_main_resume_requires_replacement_and_files_are_private(): void
    {
        Storage::fake('local');
        $first = $this->uploadResume();
        $second = $this->uploadResume();
        $this->get('/ung-vien/cv/'.$first.'/pdf')->assertOk();
        $this->post('/ung-vien/cv/'.$first.'/delete')->assertSessionHasErrors('replacement');
        $this->post('/ung-vien/cv/'.$first.'/delete', ['replacement' => $second])->assertSessionHasNoErrors();
        $this->assertSame($second, session('candidate_preview.resumes.0.id'));
        $this->assertTrue(session('candidate_preview.resumes.0.main'));
        $this->get('/ung-vien/cv/'.$first.'/pdf')->assertNotFound();
        session()->forget('candidate_preview');
        $this->get('/ung-vien/cv/'.$second.'/pdf')->assertNotFound();
    }

    public function test_upload_limit_and_skills_normalization(): void
    {
        Storage::fake('local');
        $first = $this->uploadResume();
        $this->post('/ung-vien/cv/'.$first.'/skills', ['skills' => 'php, PHP, mysql, Laravel'])->assertSessionHasNoErrors();
        $this->assertSame(['PHP', 'MySQL', 'Laravel'], session('candidate_preview.resumes.0.skills'));
        $this->assertSame('completed', session('candidate_preview.resumes.0.status'));
        for ($index = 1; $index < 10; $index++) {
            $this->uploadResume();
        }
        $file = UploadedFile::fake()->createWithContent('extra.pdf', "%PDF-1.4\n%%EOF");
        $this->post('/ung-vien/cv', ['pdf' => $file])->assertSessionHasErrors('pdf');
        $this->post('/ung-vien/cv', ['pdf' => UploadedFile::fake()->create('bad.txt', 1)])->assertSessionHasErrors('pdf');
        $this->post('/ung-vien/cv', ['pdf' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('pdf');
    }

    public function test_only_two_applications_and_pending_cv_is_accepted(): void
    {
        Storage::fake('local');
        $resume = $this->uploadResume();
        $input = ['resume' => $resume, 'confirmation' => '1', 'letter' => 'Thư minh họa'];
        $this->post('/ung-vien/ung-tuyen/1', $input)->assertSessionHasNoErrors();
        $this->post('/ung-vien/ung-tuyen/1', $input)->assertSessionHasErrors('application');
        $first = session('candidate_preview.applications.0.id');
        $this->post('/ung-vien/rut-ho-so/'.$first)->assertSessionHasNoErrors();
        $this->post('/ung-vien/ung-tuyen/1', $input)->assertSessionHasNoErrors();
        $second = session('candidate_preview.applications.1.id');
        $this->post('/ung-vien/rut-ho-so/'.$second)->assertSessionHasNoErrors();
        $this->post('/ung-vien/ung-tuyen/1', $input)->assertSessionHasErrors('application');
        $this->post('/ung-vien/ung-tuyen/9', $input)->assertSessionHasErrors('application');
        $this->assertCount(2, session('candidate_preview.applications'));
    }

    public function test_final_status_cannot_withdraw_and_notifications_can_be_read(): void
    {
        $this->post('/demo/ho-so-mau')->assertRedirect();
        $hired = collect(session('candidate_preview.applications'))->firstWhere('status', 'hired');
        $this->post('/ung-vien/rut-ho-so/'.$hired['id'])->assertSessionHasErrors('application');
        $this->post('/ung-vien/thong-bao/all')->assertRedirect();
        foreach (session('candidate_preview.notifications') as $notification) {
            $this->assertTrue($notification['read']);
        }
        $this->post('/demo/tai-khoan/register', ['email' => 'invalid', 'password' => 'short'])->assertSessionHasErrors(['email', 'password']);
    }
}
