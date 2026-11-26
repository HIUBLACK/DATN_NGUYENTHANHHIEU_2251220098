<?php

namespace Tests\Unit;

use App\Services\CompanyImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompanyImageStorageTest extends TestCase
{
    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('image.png', 10, 10);
    }

    public function test_delete_cannot_escape_company_namespace_or_touch_another_company(): void
    {
        Storage::fake('local');
        $images = app(CompanyImageStorage::class);
        $owner = (string) Str::uuid();
        $other = (string) Str::uuid();
        $owned = $images->store($this->image(), $owner, 'logo');
        $foreign = $images->store($this->image(), $other, 'logo');
        Storage::disk('local')->put('private.txt', 'Keep');
        $images->delete($owner, [$owned, $foreign, 'companies/'.$owner.'/../../private.txt', 'private.txt']);
        Storage::disk('local')->assertMissing($owned);
        Storage::disk('local')->assertExists([$foreign, 'private.txt']);
        $this->assertFalse($images->owns($owner, $foreign));
    }

    public function test_embedded_php_is_rejected_without_creating_a_file(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('image.png', $this->image()->get().'<?php echo "unsafe";');
        try {
            app(CompanyImageStorage::class)->store($file, (string) Str::uuid(), 'logo');
            $this->fail('Executable content must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('logo', $exception->errors());
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_files_get_random_names_and_actual_mime_extension(): void
    {
        Storage::fake('local');
        $images = app(CompanyImageStorage::class);
        $owner = (string) Str::uuid();
        $first = $images->store($this->image(), $owner, 'logo');
        $second = $images->store($this->image(), $owner, 'logo');
        $this->assertNotSame($first, $second);
        $this->assertTrue($images->owns($owner, $first));
        $this->assertStringEndsWith('.png', $first);
        Storage::disk('local')->assertExists([$first, $second]);
    }
}
