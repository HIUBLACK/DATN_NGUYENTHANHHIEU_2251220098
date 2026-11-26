<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use App\Support\CompanyOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    private array $userIds = [];


    private User $recruiter;

    protected function setUp(): void
    {
        parent::setUp();
        if (! preg_match('/^viejob_models_test_[a-f0-9]{12}$/', DB::connection()->getDatabaseName())) {
            $this->markTestSkipped('Run php tests/Database/models.php --auth on an isolated MariaDB database.');
        }
        Storage::fake('local');
        $this->recruiter = $this->account();
        $this->signIn($this->recruiter);
    }

    protected function tearDown(): void
    {
        if ($this->userIds) {
            Company::whereIn('recruiter_id', $this->userIds)->delete();
            User::whereIn('id', $this->userIds)->delete();
        }
        parent::tearDown();
    }

    private function account(UserRole $role = UserRole::Recruiter): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Company test', 'email' => Str::uuid().'@test.invalid',
            'password' => 'Password123', 'role' => $role, 'status' => UserStatus::Active,
        ])->save();
        $this->userIds[] = $user->id;

        return $user;
    }

    private function signIn(User $user): void
    {
        // Mỗi lần đổi tài khoản cần phiên mới, tránh hash của user trước trong auth.session.
        $this->flushSession();
        $this->actingAs($user);
    }

    private function image(string $name = 'image.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 10, 10);
    }

    private function fields(): array
    {
        return [
            'name' => 'Doanh nghiệp thử nghiệm', 'summary' => 'Mô tả công khai',
            'description' => 'Nội dung giới thiệu', 'founded_year' => 2020,
            'size' => '51–200', 'website' => 'https://example.com',
            'email' => 'private-company-contact@example.com', 'phone' => '0912345678',
            'address' => 'Địa chỉ công khai', 'ot_policy' => 'Không làm thêm giờ',
        ];
    }

    private function createCompany(array $extra = []): Company
    {
        $this->post(route('recruiter.company-store'), array_replace($this->fields(), $extra))
            ->assertRedirect(route('recruiter.company'))->assertSessionHasNoErrors();

        return Company::where('recruiter_id', auth()->id())->firstOrFail();
    }

    public function test_vietnam_address_codes_roundtrip_and_foreign_address_remains_manual(): void
    {
        $ward = \App\Support\VietnamAddress::data()['wards'][0];
        $company = $this->createCompany([
            'country' => 'Việt Nam', 'province_code' => $ward['province_code'],
            'ward_code' => $ward['code'], 'address' => '12 Đường nhập tay',
        ]);
        $this->assertSame($ward['code'], $company->ward_code);
        $this->get(route('recruiter.company-edit'))->assertOk()->assertSee($ward['name']);
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, [
            'country' => 'Việt Nam', 'province_code' => '96', 'ward_code' => $ward['code'],
        ]))->assertSessionHasErrors('ward_code');
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, [
            'country' => 'Nhật Bản', 'address' => 'Tokyo, building A',
            'province_code' => '01', 'ward_code' => $ward['code'],
        ]))->assertSessionHasNoErrors();
        $company->refresh();
        $this->assertNull($company->province_code);
        $this->assertNull($company->ward_code);
        $this->assertSame('Tokyo, building A', $company->full_address);
    }

    private function updateFields(Company $company, array $extra = []): array
    {
        return array_replace($this->fields(), ['updated_at' => $company->fresh()->getRawOriginal('updated_at')], $extra);
    }

    public function test_recruiter_creates_real_company_and_cannot_change_system_fields(): void
    {
        $other = $this->account();
        $company = $this->createCompany([
            'recruiter_id' => $other->id, 'owner_id' => $other->id, 'status' => 'locked',
            'public_id' => (string) Str::uuid(), 'id' => 999999, 'logo_path' => '../../private',
        ]);
        $this->assertSame($this->recruiter->id, $company->recruiter_id);
        $this->assertSame(CompanyStatus::Active, $company->status);
        $this->assertNull($company->logo_path);
        $this->assertNotSame(999999, $company->id);
        $this->assertNull($company->short_description);
        $this->assertNull(session('company_profile_preview'));
        $this->get(route('recruiter.company'))->assertOk()->assertSee($company->email);
        $this->get(route('recruiter.company-create'))->assertRedirect(route('recruiter.company'));
    }

    public function test_company_options_schedule_and_work_environment_use_updated_schema(): void
    {
        $company = $this->createCompany([
            'country' => 'Việt Nam', 'company_type' => CompanyOptions::TYPES[0],
            'province_code' => '01', 'ward_code' => \App\Support\VietnamAddress::data()['wards'][0]['code'],
            'working_from' => 1, 'working_to' => 5, 'no_website' => 1, 'website' => 'invalid',
        ]);
        $this->assertNull($company->website);
        $this->assertSame('Thứ Hai – Thứ Sáu', $company->working_hours);
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, ['work_environment' => 'Workplace introduction']))->assertSessionHasNoErrors();
        $this->assertSame('Workplace introduction', $company->fresh()->work_environment);
        $this->get(route('companies.show', $company->public_id))->assertOk()->assertSee('Workplace introduction');
        foreach (['short_name', 'short_description', 'founded_year', 'benefits', 'technologies', 'cover_image_path'] as $column) {
            $this->assertFalse(Schema::hasColumn('companies', $column));
        }
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, ['work_environment' => str_repeat('x', 3001)]))->assertSessionHasErrors('work_environment');
        $this->get(route('recruiter.company-edit'))->assertOk()->assertSee('company_type')->assertSee('working_from')
            ->assertDontSee('name="short_name"', false)->assertDontSee('name="cover"', false);
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, ['country' => 'Invalid country', 'company_type' => 'Invalid type', 'ot_policy' => 'Invalid OT', 'working_from' => 8]))
            ->assertSessionHasErrors(['country', 'company_type', 'ot_policy', 'working_from', 'working_to']);
        $this->assertCount(249, CompanyOptions::countries());
    }

    public function test_guest_candidate_and_admin_cannot_create_or_update(): void
    {
        $company = $this->createCompany();
        foreach ([UserRole::Candidate, UserRole::Admin] as $role) {
            $this->signIn($this->account($role));
            $this->post(route('recruiter.company-store'), $this->fields())->assertForbidden();
            $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company))->assertForbidden();
            $this->get(route('recruiter.company'))->assertForbidden();
        }
        Auth::logout();
        $this->get(route('recruiter.company'))->assertRedirect(route('login'));
        $this->post(route('recruiter.company-store'), $this->fields())->assertRedirect(route('login'));
    }

    public function test_repeated_create_does_not_create_second_company(): void
    {
        $company = $this->createCompany();
        $this->post(route('recruiter.company-store'), $this->fields() + ['logo' => $this->image()])
            ->assertSessionHasErrors('company');
        $this->assertSame(1, Company::where('recruiter_id', $this->recruiter->id)->count());
        $this->assertSame($company->public_id, Company::where('recruiter_id', $this->recruiter->id)->first()->public_id);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_owner_updates_and_forged_owner_status_and_paths_are_ignored(): void
    {
        $company = $this->createCompany();
        $this->get(route('recruiter.company-edit'))->assertOk()->assertSee('name="updated_at"', false);
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, [
            'name' => 'Tên mới', 'status' => 'locked', 'recruiter_id' => $this->account()->id,
            'logo_path' => '../../bad', 'office_images_path' => ['bad'],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('recruiter.company'));
        $company->refresh();
        $this->assertSame('Tên mới', $company->name);
        $this->assertSame($this->recruiter->id, $company->recruiter_id);
        $this->assertSame(CompanyStatus::Active, $company->status);
        $this->assertNull($company->logo_path);
    }

    public function test_other_recruiter_cannot_read_management_or_update_by_id(): void
    {
        $company = $this->createCompany(['logo' => $this->image()]);
        $this->signIn($this->account());
        $this->get(route('recruiter.company', ['company' => $company->public_id]))->assertOk()->assertDontSee($company->email);
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company))->assertForbidden();
        $this->get(route('recruiter.company-media', [$company->public_id, 'logo']))->assertForbidden();
        $this->get(route('recruiter.company-edit'))->assertRedirect(route('recruiter.company-create'));
        $this->put(route('recruiter.company-update', (string) Str::uuid()), $this->fields())->assertNotFound();
    }

    public function test_locked_company_is_read_only_for_owner_and_not_public(): void
    {
        $company = $this->createCompany(['logo' => $this->image()]);
        $company->status = CompanyStatus::Locked;
        $company->save();
        $this->get(route('recruiter.company'))->assertOk()->assertSee($company->name);
        $this->get(route('recruiter.company-edit'))->assertForbidden();
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company))->assertForbidden();
        $this->get(route('companies.show', $company->public_id))->assertNotFound();
        $this->get(route('companies.media', [$company->public_id, 'logo']))->assertNotFound();
        $this->get(route('recruiter.company-media', [$company->public_id, 'logo']))->assertOk();
        $this->get(route('companies'))->assertOk()->assertDontSee($company->name);
    }

    public function test_public_projection_hides_private_contact_paths_and_demo_data(): void
    {
        $company = $this->createCompany(['logo' => $this->image(), 'cover' => $this->image(), 'office_images' => [$this->image()]]);
        Auth::logout();
        $this->get(route('companies.show', $company->public_id))->assertOk()
            ->assertSee($company->name)->assertSee('Không làm thêm giờ')
            ->assertDontSee($company->email)->assertDontSee($company->phone)->assertDontSee($company->logo_path);
        $this->get(route('companies'))->assertOk()->assertSee(route('companies.show', $company->public_id))
            ->assertDontSee(route('companies.demo', 1));
        $this->get(route('companies.media', [$company->public_id, 'logo']))->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->recruiter->status = UserStatus::Locked;
        $this->recruiter->save();
        $this->get(route('companies.show', $company->public_id))->assertNotFound();
        $this->get(route('companies.media', [$company->public_id, 'logo']))->assertNotFound();
        $this->get(route('companies'))->assertDontSee($company->name);
    }

    public function test_invalid_fields_and_uploads_do_not_write_records_or_files(): void
    {
        $this->post(route('recruiter.company-store'), array_replace($this->fields(), [
            'name' => '  ', 'email' => 'invalid', 'phone' => '........', 'website' => 'javascript:alert(1)', 'founded_year' => 9999,
        ]))->assertSessionHasErrors(['name', 'email', 'phone', 'website']);
        foreach ([
            UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "unsafe";'),
            UploadedFile::fake()->createWithContent('image.svg', '<svg><script>alert(1)</script></svg>'),
            $this->image('image.php'),
            UploadedFile::fake()->create('too-large.png', 2049, 'image/png'),
            UploadedFile::fake()->createWithContent('polyglot.png', $this->image()->get().'<?php echo "unsafe";'),
        ] as $file) {
            $this->post(route('recruiter.company-store'), $this->fields() + ['logo' => $file])->assertSessionHasErrors('logo');
        }
        $this->assertSame(0, Company::where('recruiter_id', $this->recruiter->id)->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_gallery_update_keeps_old_logo_and_cleans_staged_files(): void
    {
        $gallery = array_map(fn ($index) => $this->image('office'.$index.'.png'), range(1, 6));
        $company = $this->createCompany(['logo' => $this->image(), 'office_images' => $gallery]);
        $oldLogo = $company->logo_path;
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, [
            'logo' => $this->image(), 'office_images' => [$this->image()],
        ]))->assertSessionHasErrors('office_images');
        $this->assertSame($oldLogo, $company->fresh()->logo_path);
        Storage::disk('local')->assertExists($oldLogo);
        $this->assertCount(7, Storage::disk('local')->allFiles());
    }

    public function test_successful_replacement_removes_only_old_owned_images_after_commit(): void
    {
        $company = $this->createCompany(['logo' => $this->image(), 'office_images' => [$this->image(), $this->image()]]);
        $previous = $company->office_images;
        $oldLogo = $company->logo_path;
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, [
            'logo' => $this->image(), 'remove_office_images' => [0], 'office_images' => [$this->image()],
        ]))->assertSessionHasNoErrors();
        $company->refresh();
        Storage::disk('local')->assertMissing([$oldLogo, $previous[0]['path']]);
        Storage::disk('local')->assertExists([$company->logo_path, $previous[1]['path']]);
        $this->assertSame($previous[1], $company->office_images[0]);
        $this->assertCount(2, $company->office_images);
    }

    public function test_stale_update_is_rejected_without_losing_current_images(): void
    {
        $company = $this->createCompany(['logo' => $this->image()]);
        $stale = $this->updateFields($company);
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, ['name' => 'Changed']))->assertSessionHasNoErrors();
        $this->put(route('recruiter.company-update', $company->public_id), $stale + ['logo' => $this->image()])->assertSessionHasErrors('company');
        $this->assertSame('Changed', $company->fresh()->name);
        Storage::disk('local')->assertExists($company->logo_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_database_failure_rolls_back_changes_and_keeps_old_logo(): void
    {
        $company = $this->createCompany(['logo' => $this->image()]);
        $dispatcher = Company::getEventDispatcher();
        Company::setEventDispatcher(clone $dispatcher);
        try {
            Company::saving(function (Company $record) {
                // Ép vi phạm CHECK tại DB để kiểm tra rollback thật, không mock transaction.
                $record->setRawAttributes(array_replace($record->getAttributes(), ['status' => 'invalid']));
            });
            $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, [
                'logo' => $this->image(), 'name' => 'Must rollback',
            ]))->assertSessionHasErrors('company');
        } finally {
            Company::setEventDispatcher($dispatcher);
        }
        $this->assertSame($company->name, $company->fresh()->name);
        Storage::disk('local')->assertExists($company->logo_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_static_industry_is_retained_and_unknown_value_is_rejected(): void
    {
        $company = $this->createCompany(['industry_id' => 1]);
        $this->get(route('recruiter.company-edit'))->assertOk()->assertSee('Gia công phần mềm');
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company, ['industry_id' => 100]))->assertSessionHasNoErrors();
        $this->assertSame(100, $company->fresh()->industry_id);
        $this->put(route('recruiter.company-update', $company->public_id), $this->updateFields($company->fresh(), ['industry_id' => 999999]))->assertSessionHasErrors('industry_id');
    }

    public function test_demo_company_pages_remain_explicit_and_separate(): void
    {
        $this->get(route('companies'))->assertOk()->assertDontSee(route('companies.demo', 1));
        $this->get(route('companies.demo', 4))->assertOk()->assertSee('Pixel Studio')
            ->assertSee('minh họa')->assertSee('Thiết kế UI/UX')->assertDontSee('Kỹ sư AI/ML');
        $this->get(route('companies.demo', 999))->assertNotFound();
    }
}
