<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TrustedProxyUploadTest extends TestCase
{
    public function test_livewire_upload_signature_is_valid_behind_the_https_proxy(): void
    {
        Storage::fake('tmp-for-tests');
        URL::forceScheme('https');
        $this->withoutExceptionHandling();

        try {
            $signedUrl = URL::temporarySignedRoute(
                'livewire.upload-file',
                now()->addMinute(),
            );

            $parts = parse_url($signedUrl);
            $requestUri = ($parts['path'] ?? '/').'?'.($parts['query'] ?? '');

            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '10.0.0.10',
                    'HTTPS' => 'off',
                    'SERVER_PORT' => 80,
                ])
                ->withHeaders([
                    'Host' => $parts['host'] ?? 'localhost',
                    'X-Forwarded-Proto' => 'https',
                    'X-Forwarded-Port' => '443',
                ])
                ->post($requestUri, [
                    'files' => [UploadedFile::fake()->image('produto.png', 32, 32)],
                ]);

            $response->assertOk();
            $uploadedFiles = array_filter(
                Storage::disk('tmp-for-tests')->files('livewire-tmp'),
                fn (string $path): bool => ! str_ends_with($path, '.json'),
            );

            $this->assertCount(1, $uploadedFiles);
        } finally {
            URL::forceScheme(null);
        }
    }

    public function test_nginx_forwards_reverse_proxy_headers_to_php(): void
    {
        $nginxTemplate = file_get_contents(
            base_path('docker/nginx/templates/default.conf.template'),
        );

        $this->assertStringContainsString(
            'fastcgi_param HTTPS on;',
            $nginxTemplate,
        );
        $this->assertStringContainsString(
            'fastcgi_param HTTP_X_FORWARDED_PROTO https;',
            $nginxTemplate,
        );
        $this->assertStringContainsString(
            'fastcgi_param HTTP_X_FORWARDED_PORT 443;',
            $nginxTemplate,
        );
    }
}
