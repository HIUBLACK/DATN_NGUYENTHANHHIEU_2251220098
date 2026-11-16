<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\AuthenticatedDemoTestCase;

class CompanyProfileTest extends AuthenticatedDemoTestCase
{
    protected UserRole $demoRole = UserRole::Recruiter;

    private function image(string $name = 'image.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1cAAAAASUVORK5CYII='));
    }

    private function profile(): array
    {
        $this->get(route('recruiter.company-edit'))->assertOk();

        return session('company_profile_preview');
    }

    private function fields(): array
    {
        return [
            'name' => 'Doanh nghiệp thử nghiệm',
            'short_name' => 'Demo',
            'summary' => 'Mô tả công khai',
            'description' => 'Nội dung giới thiệu',
            'founded_year' => 2020,
            'size' => '51–200',
            'industry' => 'CNTT',
            'company_type' => 'Sản phẩm',
            'country' => 'Việt Nam',
            'website' => 'https://example.com',
            'email' => 'private-company-contact@example.com',
            'phone' => '0912345678',
            'address' => 'Địa chỉ công khai',
            'working_hours' => 'Thứ Hai – Thứ Sáu',
            'ot_policy' => 'Không khuyến khích OT',
            'benefits' => 'Đào tạo',
            'technologies' => 'Laravel, MySQL',
        ];
    }

    public function test_demo_profile_saves_images_and_exposes_only_public_fields(): void
    {
        Storage::fake('local');
        $profile = $this->profile();
        $this->put(route('recruiter.company-update', $profile['id']), $this->fields() + [
            'logo' => $this->image(),
            'cover' => $this->image('cover.png'),
            'office_images' => [$this->image('office.png')],
            'owner_token' => 'forged-owner',
            'status' => 'blocked',
        ])->assertRedirect(route('recruiter.company-edit'))->assertSessionHasNoErrors();
        $saved = session('company_profile_preview');
        $this->assertSame($profile['owner_token'], $saved['owner_token']);
        $this->assertSame('active', $saved['status']);
        Storage::disk('local')->assertExists([$saved['logo'], $saved['cover'], $saved['office_images'][0]]);
        $this->get(route('companies.show', $saved['id']))->assertOk()
            ->assertSee('Doanh nghiệp thử nghiệm')
            ->assertSee('Mô tả công khai')
            ->assertSee('Không khuyến khích OT')
            ->assertDontSee('private-company-contact@example.com')
            ->assertDontSee('0912345678')
            ->assertDontSee($saved['owner_token'])
            ->assertDontSee($saved['logo']);
        $this->get(route('companies.media', [$saved['id'], 'logo']))->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_demo_ownership_and_blocked_profiles_cannot_be_bypassed(): void
    {
        $this->post(route('recruiter.company-store'), $this->fields())->assertForbidden();
        $profile = $this->profile();
        $this->put(route('recruiter.company-update', (string) Str::uuid()), $this->fields())->assertForbidden();
        $this->withSession(['company_profile_owner' => 'other-owner'])
            ->put(route('recruiter.company-update', $profile['id']), $this->fields())->assertForbidden();
        $profile['status'] = 'blocked';
        $this->withSession(['company_profile_preview' => $profile, 'company_profile_owner' => $profile['owner_token']])
            ->put(route('recruiter.company-update', $profile['id']), $this->fields())->assertForbidden();
        $this->get(route('companies.show', $profile['id']))->assertNotFound();
        $this->get(route('companies.media', [$profile['id'], 'logo']))->assertNotFound();
        $this->withSession(['company_profile_preview' => null, 'company_profile_owner' => null])
            ->get(route('companies.show', $profile['id']))->assertNotFound();
    }

    public function test_server_rejects_invalid_fields_and_disallowed_uploads(): void
    {
        Storage::fake('local');
        $profile = $this->profile();
        $url = route('recruiter.company-update', $profile['id']);
        $this->put($url, array_replace($this->fields(), ['name' => '  ', 'website' => 'javascript:alert(1)', 'founded_year' => 9999]))
            ->assertSessionHasErrors(['name', 'website', 'founded_year']);
        foreach ([
            UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "unsafe";'),
            UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            $this->image('image.php'),
            UploadedFile::fake()->create('too-large.png', 2049, 'image/png'),
        ] as $file) {
            $this->put($url, $this->fields() + ['logo' => $file])->assertSessionHasErrors('logo');
        }
        $polyglot = UploadedFile::fake()->createWithContent('polyglot.png', $this->image()->get().'<?php echo "unsafe";');
        $this->put($url, $this->fields() + ['logo' => $polyglot])->assertSessionHasErrors('logo');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_replacement_removal_and_total_gallery_limit(): void
    {
        Storage::fake('local');
        $profile = $this->profile();
        $url = route('recruiter.company-update', $profile['id']);
        $gallery = array_map(fn ($index) => $this->image('office'.$index.'.png'), range(1, 6));
        $this->put($url, $this->fields() + ['logo' => $this->image(), 'office_images' => $gallery])->assertSessionHasNoErrors();
        $previous = session('company_profile_preview');
        $this->put($url, $this->fields() + ['logo' => $this->image(), 'office_images' => [$this->image()]])
            ->assertSessionHasErrors('office_images');
        $this->assertCount(7, Storage::disk('local')->allFiles());
        $this->assertSame($previous['logo'], session('company_profile_preview.logo'));
        $this->put($url, $this->fields() + ['remove_logo' => 1, 'remove_office_images' => [0], 'office_images' => [$this->image()]])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing([$previous['logo'], $previous['office_images'][0]]);
        $this->assertCount(6, session('company_profile_preview.office_images'));
    }

    public function test_sample_company_pages_list_only_open_public_jobs(): void
    {
        $this->get(route('companies'))->assertOk()->assertSee(route('companies.demo', 1));
        $this->get(route('companies.demo', 4))->assertOk()->assertSee('Pixel Studio')
            ->assertSee('Thiết kế UI/UX')->assertDontSee('Kỹ sư AI/ML');
        $this->get(route('companies.demo', 999))->assertNotFound();
    }
}
