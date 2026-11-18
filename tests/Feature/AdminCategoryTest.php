<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\JobSpecialization;
use App\Models\Skill;
use App\Services\CategoryManagementService;
use App\Services\PublicJobService;
use App\Services\RecruiterJobService;
use App\Support\AdminCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;

class AdminCategoryTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;

    protected UserRole $demoRole = UserRole::Admin;

    private function input(string $name, array $extra = []): array
    {
        return $extra + ['name' => $name, 'description' => 'Mô tả tiếng Việt', 'is_active' => 1, 'sort_order' => 0];
    }

    private function createCategory(string $type, string $name, array $extra = [])
    {
        $this->post(route('admin.'.$type.'.store'), $this->input($name, $extra))->assertSessionHasNoErrors()->assertRedirect();
        [$model] = AdminCategory::definition($type);

        return $model::latest('id')->firstOrFail();
    }

    private function token($record): array
    {
        return ['updated_at' => $record->fresh()->getRawOriginal('updated_at')];
    }

    public function test_admin_manages_all_five_catalogs_without_demo_or_deletion(): void
    {
        $parent = $this->createCategory('occupations', 'Nhóm cha');
        foreach (AdminCategory::TYPES as $type => [$model]) {
            $extra = $type === 'specialties' ? ['job_category_id' => $parent->id] : [];
            $this->get(route('admin.'.$type.'.index'))->assertOk()->assertDontSee('admin-assets/js/admin-categories.js');
            $this->get(route('admin.'.$type.'.create'))->assertOk();
            $record = $this->createCategory($type, 'Danh mục '.$type, $extra + ['id' => 999999, 'code' => 'FORGED', 'slug' => 'forged', 'normalized_name' => 'forged']);
            $this->assertNotEquals(999999, $record->id);
            $this->assertNotSame('FORGED', $record->code);
            $this->assertLessThanOrEqual(20, strlen($record->code));
            $code = $record->code;
            $slug = $record->slug;
            $this->get(route('admin.'.$type.'.edit', $record->id))->assertOk()->assertSee($record->name);
            $this->put(route('admin.'.$type.'.update', $record->id), $this->input('Tên mới '.$type, $extra + $this->token($record)))
                ->assertSessionHasNoErrors();
            $this->assertSame($code, $record->fresh()->code);
            $this->assertSame($slug, $record->fresh()->slug);
            foreach ([0, 1] as $active) {
                $this->patch(route('admin.'.$type.'.status', $record->id), ['is_active' => $active] + $this->token($record))->assertSessionHasNoErrors();
                $this->assertSame((bool) $active, $record->fresh()->is_active);
            }
            $this->delete(route('admin.'.$type.'.update', $record->id))->assertStatus(405);
            $this->assertNotNull($record->fresh());
        }
    }

    public function test_unicode_normalization_preserves_accents_and_skill_symbols(): void
    {
        $this->createCategory('skills', "  Lập\u{00A0}trình  ");
        $this->post(route('admin.skills.store'), $this->input("LA\u{0323}\u{0302}P TRÌNH"))->assertSessionHasErrors('name');
        $this->createCategory('skills', 'Lap trinh');
        foreach (['C', 'C++', 'C#', '.NET'] as $name) {
            $this->createCategory('skills', $name);
        }
        $this->assertSame(6, Skill::count());
        foreach ([" \t\n", "\u{200B}", str_repeat('x', 121)] as $name) {
            $this->post(route('admin.skills.store'), $this->input($name))->assertSessionHasErrors('name');
        }
        $first = $this->createCategory('levels', 'C++');
        $second = $this->createCategory('levels', 'C#');
        $this->assertNotSame($first->slug, $second->slug);
        $this->assertLessThanOrEqual(160, strlen($second->slug));
    }

    public function test_duplicates_inactive_records_and_stale_forms_are_rejected(): void
    {
        $record = $this->createCategory('skills', 'Docker');
        $old = $this->token($record);
        $this->patch(route('admin.skills.status', $record->id), ['is_active' => 0] + $old)->assertSessionHasNoErrors();
        $this->post(route('admin.skills.store'), $this->input('DOCKER'))->assertSessionHasErrors('name');
        $this->put(route('admin.skills.update', $record->id), $this->input('Overwrite', $old))->assertSessionHasErrors('category');
        $this->assertSame('Docker', $record->fresh()->name);
        $this->put(route('admin.skills.update', 999999), $this->input('Missing', $old))->assertNotFound();
        $this->post(route('admin.levels.store'), $this->input('Invalid parent', ['job_category_id' => 1]))->assertSessionHasErrors('job_category_id');
        $this->post(route('admin.levels.store'), $this->input('Invalid order', ['sort_order' => 65536]))->assertSessionHasErrors('sort_order');
    }

    public function test_hierarchy_constraints_and_scoped_uniqueness(): void
    {
        $first = $this->createCategory('occupations', 'Phần mềm');
        $second = $this->createCategory('occupations', 'Dữ liệu');
        $specialty = $this->createCategory('specialties', 'Developer', ['job_category_id' => $first->id]);
        $this->createCategory('specialties', 'Developer', ['job_category_id' => $second->id]);
        $this->post(route('admin.specialties.store'), $this->input('developer', ['job_category_id' => $first->id]))->assertSessionHasErrors('name');
        $this->patch(route('admin.occupations.status', $first->id), ['is_active' => 0] + $this->token($first))->assertSessionHasErrors('is_active');
        $this->put(route('admin.occupations.update', $first->id), $this->input('Phần mềm', ['is_active' => 0] + $this->token($first)))->assertSessionHasErrors('is_active');
        $this->patch(route('admin.specialties.status', $specialty->id), ['is_active' => 0] + $this->token($specialty))->assertSessionHasNoErrors();
        $this->patch(route('admin.occupations.status', $first->id), ['is_active' => 0] + $this->token($first))->assertSessionHasNoErrors();
        $this->patch(route('admin.specialties.status', $specialty->id), ['is_active' => 1] + $this->token($specialty))->assertSessionHasErrors('job_category_id');
        foreach ([$first->id, 999999] as $parent) {
            $this->post(route('admin.specialties.store'), $this->input('New', ['job_category_id' => $parent]))->assertSessionHasErrors('job_category_id');
        }
        $this->get(route('admin.specialties.index', ['parent' => $first->id]))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $this->put(route('admin.specialties.update', $specialty->id), $this->input('Moved developer', ['job_category_id' => $second->id] + $this->token($specialty)))
            ->assertSessionHasNoErrors();
        $this->assertSame($second->id, $specialty->fresh()->job_category_id);
    }

    public function test_roles_guest_and_locked_admin_cannot_write_or_read(): void
    {
        foreach ([UserRole::Candidate, UserRole::Recruiter] as $role) {
            $this->flushSession();
            $this->loginDemoUser($role);
            foreach (array_keys(AdminCategory::TYPES) as $type) {
                $this->get(route('admin.'.$type.'.index'))->assertForbidden();
                $this->post(route('admin.'.$type.'.store'), $this->input('Forbidden'))->assertForbidden();
                $this->put(route('admin.'.$type.'.update', 1), $this->input('Forbidden'))->assertForbidden();
                $this->patch(route('admin.'.$type.'.status', 1), ['is_active' => 0])->assertForbidden();
            }
        }
        Auth::logout();
        $this->get(route('admin.skills.index'))->assertRedirect(route('login'));
        $this->post(route('admin.skills.store'), $this->input('Guest'))->assertRedirect(route('login'));
        $this->loginDemoUser(UserRole::Admin);
        $user = auth()->user();
        $user->status = 'locked';
        $user->save();
        $this->post(route('admin.skills.store'), $this->input('Locked'))->assertRedirect(route('login'));
        $this->assertSame(0, Skill::count());
    }

    public function test_pagination_sort_and_literal_search(): void
    {
        foreach (range(1, 17) as $number) {
            app(CategoryManagementService::class)->save(auth()->user(), 'skills', null, $this->input('Skill '.$number, ['sort_order' => $number]));
        }
        $this->createCategory('skills', '100% SQL', ['is_active' => 0]);
        $this->get(route('admin.skills.index'))->assertViewHas('records', fn ($rows) => $rows->total() === 18 && $rows->count() === 15);
        $this->get(route('admin.skills.index', ['page' => 2]))->assertViewHas('records', fn ($rows) => $rows->count() === 3);
        $this->get(route('admin.skills.index', ['q' => '%', 'status' => 'inactive']))->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $this->get(route('admin.skills.index', ['sort' => 'name']))->assertOk();
    }

    public function test_admin_created_catalogs_are_available_to_recruiter_and_candidate(): void
    {
        $parent = $this->createCategory('occupations', 'Nhóm thật');
        $specialty = $this->createCategory('specialties', 'Chuyên môn thật', ['job_category_id' => $parent->id]);
        $skill = $this->createCategory('skills', 'Kỹ năng thật');
        $options = app(RecruiterJobService::class)->catalogOptions();
        $this->assertTrue($options['categories']->contains('id', $parent->id));
        $this->assertTrue($options['specializations']->contains('id', $specialty->id));
        $this->assertTrue($options['skills']->contains('id', $skill->id));
        $navigation = app(PublicJobService::class)->navigation();
        $this->assertContains($skill->name, array_column($navigation['jobCategories']['skills'], 'name'));
        $this->patch(route('admin.skills.status', $skill->id), ['is_active' => 0] + $this->token($skill))->assertSessionHasNoErrors();
        $navigation = app(PublicJobService::class)->navigation();
        $this->assertNotContains($skill->name, array_column($navigation['jobCategories']['skills'], 'name'));
        $this->assertFalse(app(RecruiterJobService::class)->catalogOptions()['skills']->contains('id', $skill->id));
    }

    public function test_referenced_specialty_and_hidden_skills_preserve_job_history_and_integration(): void
    {
        $admin = auth()->user();
        $newParent = $this->createCategory('occupations', 'Other group');
        $this->loginDemoUser(UserRole::Recruiter);
        $recruiter = auth()->user();
        $this->companyFixture($recruiter->id);
        $input = $this->completeInput();
        $service = app(RecruiterJobService::class);
        $job = $service->create($recruiter, $input);
        $version = $job->versions()->first();
        $specialty = JobSpecialization::findOrFail($input['job_specialization_id']);
        $this->actingAs($admin);
        $this->put(route('admin.specialties.update', $specialty->id), $this->input($specialty->name, ['job_category_id' => $newParent->id] + $this->token($specialty)))
            ->assertSessionHasErrors('job_category_id');
        $this->assertSame($input['job_category_id'], $specialty->fresh()->job_category_id);
        $skill = Skill::findOrFail($input['skills'][0]);
        $this->patch(route('admin.skills.status', $skill->id), ['is_active' => 0] + $this->token($skill))->assertSessionHasNoErrors();
        $this->assertSame($skill->name, $version->fresh()->skills->first()->name);
        $this->assertFalse($service->catalogOptions()['skills']->contains('id', $skill->id));
        $this->assertTrue($service->catalogOptions($version)['skills']->contains('id', $skill->id));
        $this->flushSession();
        $this->actingAs($recruiter);
        $this->get(route('recruiter.job-create'))->assertOk();
        try {
            $service->submit($recruiter, $job->id, ['version_id' => $version->id, 'updated_at' => $version->getRawOriginal('updated_at')]);
            $this->fail('Hidden skill must prevent submission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('skills', $exception->errors());
        }
        $this->assertSame('draft', $version->fresh()->review_status->value);
        $this->flushSession();
        $this->actingAs($admin);
        $this->patch(route('admin.skills.status', $skill->id), ['is_active' => 1] + $this->token($skill))->assertSessionHasNoErrors();
        $this->publishFixture($job);
        $this->assertSame(1, app(PublicJobService::class)->search(['skill' => $skill->name])->count());
        $this->get(route('jobs', ['skill' => $skill->name]))->assertOk()->assertSee($input['title']);
        $this->patch(route('admin.skills.status', $skill->id), ['is_active' => 0] + $this->token($skill))->assertSessionHasNoErrors();
        $this->get(route('jobs.show', $job->id))->assertOk()->assertSee($skill->name);
        $this->assertSame($skill->id, $version->fresh()->skills->first()->id);
    }
}
