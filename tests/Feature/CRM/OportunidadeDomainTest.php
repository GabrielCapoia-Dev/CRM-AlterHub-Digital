<?php

namespace Tests\Feature\CRM;

use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\OportunidadeProduto;
use App\Models\Produto;
use App\Models\Status\StatusCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OportunidadeDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_a_reason_when_moving_to_a_closing_stage(): void
    {
        [$user, $cliente, $lead, $perdido] = $this->criarBaseDeOportunidade();

        $oportunidade = Oportunidade::create([
            'titulo' => 'Oportunidade em andamento',
            'cliente_id' => $cliente->id,
            'etapa_id' => $lead->id,
            'user_id' => $user->id,
            'temperatura' => 'warm',
        ]);

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        $oportunidade->update([
            'etapa_id' => $perdido->id,
        ]);
    }

    public function test_it_tracks_stage_changes_and_preserves_history_rules(): void
    {
        [$user, $cliente, $lead, $perdido, $negociacao] = $this->criarBaseDeOportunidadeComNegociacao();

        $oportunidade = Oportunidade::create([
            'titulo' => 'Oportunidade principal',
            'cliente_id' => $cliente->id,
            'etapa_id' => $lead->id,
            'user_id' => $user->id,
            'temperatura' => 'hot',
            'valor_estimado' => 1999.90,
        ]);

        $this->actingAs($user);

        $oportunidade->update([
            'notas' => 'Contato inicial registrado.',
        ]);

        $this->assertDatabaseCount('oportunidade_movimentacoes', 0);

        $oportunidade->update([
            'etapa_id' => $negociacao->id,
        ]);

        $this->assertDatabaseHas('oportunidade_movimentacoes', [
            'oportunidade_id' => $oportunidade->id,
            'user_id' => $user->id,
            'etapa_origem_id' => $lead->id,
            'etapa_destino_id' => $negociacao->id,
            'motivo' => null,
        ]);

        $oportunidade->update([
            'etapa_id' => $perdido->id,
            'motivo_fechamento' => 'Cliente optou por concorrente.',
        ]);

        $this->assertDatabaseHas('oportunidade_movimentacoes', [
            'oportunidade_id' => $oportunidade->id,
            'user_id' => $user->id,
            'etapa_origem_id' => $negociacao->id,
            'etapa_destino_id' => $perdido->id,
            'motivo' => 'Cliente optou por concorrente.',
        ]);

        $this->assertSame('Cliente optou por concorrente.', $oportunidade->fresh()->motivo_fechamento);

        $oportunidade->update([
            'etapa_id' => $lead->id,
        ]);

        $this->assertDatabaseHas('oportunidade_movimentacoes', [
            'oportunidade_id' => $oportunidade->id,
            'user_id' => $user->id,
            'etapa_origem_id' => $perdido->id,
            'etapa_destino_id' => $lead->id,
            'motivo' => null,
        ]);

        $this->assertNull($oportunidade->fresh()->motivo_fechamento);
        $this->assertCount(3, $oportunidade->fresh()->oportunidadeMovimentacoes);
    }

    public function test_it_recalculates_estimated_value_from_linked_products(): void
    {
        [$user, $cliente, $lead] = $this->criarBaseDeOportunidade();

        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-EST-01',
            'nome' => 'Produto com desconto',
            'status' => 'ativo',
            'ativo' => true,
            'preco_tabela' => 100,
            'preco_minimo' => 80,
        ]);

        $oportunidade = Oportunidade::query()->create([
            'titulo' => 'Oportunidade calculada',
            'cliente_id' => $cliente->id,
            'etapa_id' => $lead->id,
            'user_id' => $user->id,
            'temperatura' => 'warm',
            'valor_estimado' => 999,
        ]);

        $link = OportunidadeProduto::query()->create([
            'oportunidade_id' => $oportunidade->id,
            'produto_id' => $produto->id,
            'quantidade' => 3,
            'preco_negociado' => 90,
            'desconto_percentual' => 10,
        ]);

        $this->assertSame(270.0, (float) $oportunidade->fresh()->valor_estimado);

        $link->update(['quantidade' => 2]);

        $this->assertSame(180.0, (float) $oportunidade->fresh()->valor_estimado);

        $link->delete();

        $this->assertNull($oportunidade->fresh()->valor_estimado);
    }

    public function test_it_requires_discount_approval_when_price_is_below_product_minimum(): void
    {
        [$user, $cliente, $lead] = $this->criarBaseDeOportunidade();

        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-DESC-01',
            'nome' => 'Produto com minimo',
            'status' => 'ativo',
            'ativo' => true,
            'preco_tabela' => 100,
            'preco_minimo' => 90,
        ]);

        $oportunidade = Oportunidade::query()->create([
            'titulo' => 'Oportunidade desconto',
            'cliente_id' => $cliente->id,
            'etapa_id' => $lead->id,
            'user_id' => $user->id,
            'temperatura' => 'warm',
        ]);

        $this->expectException(ValidationException::class);

        try {
            OportunidadeProduto::query()->create([
                'oportunidade_id' => $oportunidade->id,
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'preco_negociado' => 80,
                'desconto_percentual' => 20,
            ]);
        } finally {
            $aprovado = OportunidadeProduto::query()->create([
                'oportunidade_id' => $oportunidade->id,
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'preco_negociado' => 80,
                'desconto_percentual' => 20,
                'desconto_aprovado_por' => $user->id,
                'desconto_aprovado_em' => now(),
            ]);

            $this->assertSame($user->id, $aprovado->desconto_aprovado_por);
        }
    }

    private function criarBaseDeOportunidade(): array
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuário CRM',
            'email' => 'crm.' . Str::random(8) . '@teste.com',
            'email_approved' => true,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $status = StatusCliente::create(['nome' => 'Ativo']);
        $segmento = CategoriaSegmento::create(['nome' => 'Laboratório']);

        $cliente = Cliente::create([
            'razao_social' => 'Cliente Teste Ltda',
            'nome_fantasia' => 'Cliente Teste',
            'cnpj' => '12.345.678/0001-90',
            'id_status_cliente' => $status->id,
            'id_categoria_segmento' => $segmento->id,
            'nome_completo' => 'Contato Teste',
            'email' => 'cliente@teste.com',
        ]);

        $lead = Etapa::create([
            'nome' => 'Lead',
            'slug' => 'lead',
            'ordem' => 1,
            'fechamento' => false,
        ]);

        $perdido = Etapa::create([
            'nome' => 'Perdido',
            'slug' => 'perdido',
            'ordem' => 99,
            'fechamento' => true,
        ]);

        return [$user, $cliente, $lead, $perdido];
    }

    private function criarBaseDeOportunidadeComNegociacao(): array
    {
        [$user, $cliente, $lead, $perdido] = $this->criarBaseDeOportunidade();

        $negociacao = Etapa::create([
            'nome' => 'Negociação',
            'slug' => 'negociacao',
            'ordem' => 2,
            'fechamento' => false,
        ]);

        return [$user, $cliente, $lead, $perdido, $negociacao];
    }
}
