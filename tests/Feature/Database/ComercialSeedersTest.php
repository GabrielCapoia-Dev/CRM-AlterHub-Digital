<?php

namespace Tests\Feature\Database;

use App\Models\Oportunidade;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComercialSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_catalogs_and_crm_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(14, \App\Models\Clientes\Cliente::query()->count());
        $this->assertGreaterThanOrEqual(14, \App\Models\Produtos\Insumo::query()->count());
        $this->assertGreaterThanOrEqual(11, \App\Models\Produto::query()->count());
        $this->assertGreaterThanOrEqual(9, \App\Models\Empresas\Fornecedor::query()->count());
        $this->assertGreaterThanOrEqual(12, Oportunidade::query()->count());
        $this->assertGreaterThanOrEqual(20, \App\Models\OportunidadeMovimentacao::query()->count());

        $reaberta = Oportunidade::query()
            ->where('titulo', 'Piloto de painel veterinario multiplex')
            ->firstOrFail();

        $this->assertNull($reaberta->motivo_fechamento);
        $this->assertTrue(
            $reaberta->oportunidadeMovimentacoes()
                ->whereHas('etapaOrigem', fn ($query) => $query->where('slug', 'perdido'))
                ->whereHas('etapaDestino', fn ($query) => $query->where('slug', 'qualificado'))
                ->exists()
        );

        $retrocesso = Oportunidade::query()
            ->where('titulo', 'Escopo revisado para laboratorio universitario')
            ->firstOrFail();

        $this->assertTrue(
            $retrocesso->oportunidadeMovimentacoes()
                ->whereHas('etapaOrigem', fn ($query) => $query->where('slug', 'negociacao'))
                ->whereHas('etapaDestino', fn ($query) => $query->where('slug', 'proposta'))
                ->exists()
        );

        $ganha = Oportunidade::query()
            ->where('titulo', 'Implantacao de extracao automatizada em P&D')
            ->firstOrFail();

        $this->assertSame('ganho', $ganha->etapa->slug);
        $this->assertNotNull($ganha->motivo_fechamento);
    }
}
