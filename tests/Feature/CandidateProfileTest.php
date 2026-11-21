<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\AuthenticatedDemoTestCase;

class CandidateProfileTest extends AuthenticatedDemoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $connection = DB::connection();
        $manager = new DatabaseTransactionsManager([$connection->getName()]);
        $this->app->instance('db.transactions', $manager);
        $connection->setTransactionManager($manager);
        $manager->begin($connection->getName(), 1);
    }

    private function input(): array
    {
        return [
            'name' => 'Nguyễn Minh Anh', 'phone' => '+84 901 234 567',
            'location' => 'Đà Nẵng', 'profession' => 'Backend Developer',
            'experience' => 'Hai năm', 'bio' => 'Giới thiệu hồ sơ thật.',
        ];
    }

    public function test_profile_reads_account_and_database_fields_without_creating_on_get(): void
    {
        $user = auth()->user();
        $this->get(route('candidate.profile'))->assertOk()->assertSee($user->email)->assertDontSee('Cập nhật hồ sơ demo');
        $this->assertSame(0, CandidateProfile::count());
        $this->post(route('candidate.profile-save'), $this->input())->assertSessionHasNoErrors();
        $this->get(route('candidate.profile'))->assertOk()->assertSee('Giới thiệu hồ sơ thật.')
            ->assertSee('readonly', false)->assertDontSee('name="email"', false);
        $defaultAvatar = $this->get(route('candidate.avatar'))->assertOk();
        $this->assertSame(realpath(public_path('jobboard/images/person_1.jpg')), $defaultAvatar->baseResponse->getFile()->getRealPath());
        $user->forceFill(['google_subject' => 'profile-test-subject', 'has_local_password' => false])->save();
        $this->get(route('candidate.profile'))->assertOk()->assertSee('Phương thức đăng nhập: Google')->assertDontSee('profile-test-subject');
    }

    public function test_valid_update_persists_and_keeps_identity_and_authentication_fields(): void
    {
        $user = auth()->user();
        $before = $user->only(['email', 'password', 'role', 'status', 'google_subject', 'has_local_password']);
        $this->post(route('candidate.profile-save'), $this->input())->assertRedirect(route('candidate.profile'))->assertSessionHasNoErrors();
        $profile = CandidateProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Backend Developer', $profile->professional_title);
        $this->assertSame('Hai năm', $profile->experience_summary);
        $this->assertSame($before, $user->fresh()->only(array_keys($before)));
        $this->post(route('candidate.profile-save'), array_replace($this->input(), ['name' => 'Tên mới', 'bio' => null]))->assertSessionHasNoErrors();
        $this->assertSame(1, CandidateProfile::count());
        $this->assertNull($profile->fresh()->summary);
        $this->get(route('candidate.profile'))->assertOk()->assertSee('Tên mới')->assertSee('Đà Nẵng');
    }

    public function test_validation_preserves_input_and_rejects_sensitive_fields(): void
    {
        $user = auth()->user();
        $this->from(route('candidate.profile'))->post(route('candidate.profile-save'), array_replace($this->input(), [
            'name' => str_repeat('x', 256), 'phone' => 'not-a-phone', 'bio' => str_repeat('x', 1501),
        ]))->assertSessionHasErrors(['name', 'phone', 'bio'])->assertSessionHasInput('profession', 'Backend Developer');
        $this->post(route('candidate.profile-save'), $this->input() + [
            'role' => 'admin', 'status' => 'locked', 'email' => 'forged@test.invalid',
            'password' => 'Changed123', 'avatar_path' => '../../.env', 'google_subject' => 'forged',
        ])->assertSessionHasErrors(['role', 'status', 'email', 'password', 'avatar_path', 'google_subject']);
        $this->assertSame(0, CandidateProfile::count());
        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_other_candidates_roles_guests_and_locked_users_cannot_edit_another_profile(): void
    {
        $first = auth()->user();
        $this->post(route('candidate.profile-save'), $this->input())->assertSessionHasNoErrors();
        $this->flushSession();
        $this->loginDemoUser(UserRole::Candidate);
        $this->post(route('candidate.profile-save'), $this->input() + ['user_id' => $first->id])->assertSessionHasErrors('user_id');
        $this->assertSame(1, CandidateProfile::count());
        $this->get(route('candidate.profile', ['user_id' => $first->id]))->assertOk()
            ->assertViewHas('profileData', fn ($data) => $data['email'] === auth()->user()->email && $data['bio'] === null);
        foreach ([UserRole::Recruiter, UserRole::Admin] as $role) {
            $this->flushSession();
            $this->loginDemoUser($role);
            $this->post(route('candidate.profile-save'), $this->input())->assertForbidden();
            $this->get(route('candidate.avatar'))->assertForbidden();
        }
        $this->flushSession();
        $this->actingAs($first);
        DB::table('users')->where('id', $first->id)->update(['status' => 'locked']);
        $this->postJson(route('candidate.profile-save'), $this->input())->assertForbidden();
        $this->post(route('candidate.profile-save'), $this->input())->assertRedirect(route('login'));
    }

    public function test_avatar_validation_private_access_replacement_removal_and_rollback(): void
    {
        $user = auth()->user();
        $url = route('candidate.profile-save');
        $this->post($url, $this->input() + ['avatar' => UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo 1;')])->assertSessionHasErrors('avatar');
        $this->post($url, $this->input() + ['avatar' => UploadedFile::fake()->image('large.png', 5001, 1)])->assertSessionHasErrors('avatar');
        $this->post($url, $this->input() + ['avatar' => UploadedFile::fake()->image('large.jpg')->size(2049)])->assertSessionHasErrors('avatar');
        $file = UploadedFile::fake()->image('avatar.png', 120, 120);
        file_put_contents($file->getRealPath(), "\n<?php echo 1;", FILE_APPEND);
        $this->post($url, $this->input() + ['avatar' => $file])->assertSessionHasErrors('avatar');
        $this->post($url, $this->input() + ['avatar' => UploadedFile::fake()->image('avatar.png')])->assertSessionHasNoErrors();
        $profile = CandidateProfile::where('user_id', $user->id)->firstOrFail();
        $firstPath = $profile->avatar_path;
        Storage::disk('local')->assertExists($firstPath);
        $this->get(route('candidate.avatar'))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertStreamedContent(Storage::disk('local')->get($firstPath));
        $this->flushSession();
        $this->loginDemoUser(UserRole::Candidate);
        $defaultAvatar = $this->get(route('candidate.avatar', ['user_id' => $user->id, 'path' => $firstPath]))->assertOk();
        $this->assertSame(realpath(public_path('jobboard/images/person_1.jpg')), $defaultAvatar->baseResponse->getFile()->getRealPath());
        $this->flushSession();
        $this->actingAs($user);
        $this->post($url, $this->input() + ['avatar' => UploadedFile::fake()->image('avatar.webp')])->assertSessionHasNoErrors();
        $secondPath = $profile->fresh()->avatar_path;
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);
        CandidateProfile::saving(function () {
            throw new \RuntimeException('Test profile persistence failure');
        });
        try {
            $this->post($url, $this->input() + ['avatar' => UploadedFile::fake()->image('avatar.jpg')])->assertStatus(500);
        } finally {
            CandidateProfile::flushEventListeners();
        }
        $this->assertSame($secondPath, $profile->fresh()->avatar_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('candidate-avatars'));
        $this->post($url, $this->input() + ['remove_avatar' => 1])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($secondPath);
        $this->assertNull($profile->fresh()->avatar_path);
    }
}
