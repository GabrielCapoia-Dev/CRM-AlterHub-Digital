<?php

namespace Tests\Feature\Routing;

use Tests\TestCase;

class LegacyAnalyticsRedirectTest extends TestCase
{
    public function test_legacy_analytics_urls_redirect_permanently(): void
    {
        $redirects = [
            '/painel/operacao/dashboard-bi' => '/painel/dashboard/visao-geral',
            '/painel/operacao/resultado' => '/painel/dashboard/dre',
            '/painel/operacao/lucro-por-produto' => '/painel/dashboard/produtos',
        ];

        foreach ($redirects as $source => $destination) {
            $response = $this->get($source);

            $response->assertStatus(301);

            $this->assertSame(
                $destination,
                parse_url((string) $response->headers->get('Location'), PHP_URL_PATH),
            );
        }
    }
}
