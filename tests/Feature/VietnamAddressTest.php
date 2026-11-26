<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Services\RecruiterJobService;
use App\Support\Provinces;
use App\Support\VietnamAddress;
use Illuminate\Support\Facades\Validator;
use Tests\AuthenticatedDemoTestCase;
use Tests\Support\JobFixtures;

class VietnamAddressTest extends AuthenticatedDemoTestCase
{
    use JobFixtures;

    protected UserRole $demoRole = UserRole::Recruiter;

    public function test_dataset_codes_and_parents_are_complete(): void
    {
        $data = VietnamAddress::data();
        $this->assertCount(34, $data['provinces']);
        $this->assertCount(3321, $data['wards']);
        $this->assertCount(3321, array_unique(array_column($data['wards'], 'code')));
        foreach ($data['provinces'] as $province) {
            $this->assertArrayHasKey($province['code'], Provinces::ALL);
        }
        foreach ($data['wards'] as $ward) {
            $this->assertMatchesRegularExpression('/^\d{5}$/', $ward['code']);
            $this->assertArrayHasKey($ward['province_code'], Provinces::ALL);
        }
    }

    public function test_wrong_parent_and_unknown_code_are_rejected(): void
    {
        $ward = VietnamAddress::data()['wards'][0];
        $this->assertTrue(Validator::make(['ward_code' => $ward['code']], [
            'ward_code' => [VietnamAddress::wardRule('96')],
        ])->fails());
        $this->assertTrue(Validator::make(['ward_code' => '99999'], [
            'ward_code' => [VietnamAddress::wardRule('01')],
        ])->fails());
    }

    public function test_job_address_roundtrip_keeps_detail_and_codes_separate(): void
    {
        $this->companyFixture(auth()->id());
        $service = app(RecruiterJobService::class);
        $input = $this->completeInput();
        $input['location'] = '12 Đường thử nghiệm, Tòa nhà A';
        $job = $service->create(auth()->user(), $input);
        $version = $service->latest($job);
        $form = $service->formData($version);
        $this->assertSame($input['location'], $form['location']);
        $this->assertSame($input['ward_code'], $form['ward_code']);
        $this->get(route('recruiter.job-edit', $job->id))->assertOk()
            ->assertSee('name="ward_code"', false)->assertSee($input['location']);
        $service->update(auth()->user(), $job->id, $form);
        $this->assertSame($input['location'], $version->fresh()->location);
        $this->assertSame($version->full_location, $version->fresh()->full_location);
    }

    public function test_legacy_address_is_not_parsed_or_duplicated(): void
    {
        $original = '12 Tràng Tiền, Hoàn Kiếm, Hà Nội';
        $this->assertSame($original, VietnamAddress::format($original, '01', null));
        $this->assertSame($original, VietnamAddress::format($original, null, '99999'));
    }
}
