<?php

namespace Tests\Unit\Support\Ui;

use App\Support\Ui\LivewireTemporaryUploadResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

class LivewireTemporaryUploadResolverTest extends TestCase
{
    public function test_it_restores_serialized_livewire_uploads_before_the_action_uses_them(): void
    {
        Storage::fake('tmp-for-tests');
        $source = UploadedFile::fake()->image('produto.png', 32, 32);
        $filename = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($source);

        Storage::disk('tmp-for-tests')->put(
            FileUploadConfiguration::path($filename),
            $source->getContent(),
        );

        $files = LivewireTemporaryUploadResolver::resolve([
            'livewire-file:'.$filename,
        ]);

        $this->assertCount(1, $files);
        $this->assertInstanceOf(TemporaryUploadedFile::class, $files[0]);
        $this->assertSame('produto.png', $files[0]->getClientOriginalName());
    }

    public function test_it_rejects_an_expired_or_unknown_upload_reference(): void
    {
        $this->expectException(ValidationException::class);

        LivewireTemporaryUploadResolver::resolve(['arquivo-inexistente.png']);
    }
}
