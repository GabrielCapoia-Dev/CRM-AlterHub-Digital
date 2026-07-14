<?php

namespace Tests\Feature\Logistica;

use App\Enum\ModalidadeEntrega;
use App\Models\Clientes\Cliente;
use App\Models\Transportadora;
use App\Services\Logistica\FreteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_manages_client_carrier_defaults_and_returns_shipment_payload(): void
    {
        $cliente = Cliente::query()->create(['razao_social' => 'Cliente Logistica']);
        $primeira = Transportadora::query()->create([
            'razao_social' => 'Transportadora Um',
            'valor_frete_custo_padrao' => 40,
            'valor_frete_cobrado_padrao' => 55,
        ]);
        $segunda = Transportadora::query()->create([
            'razao_social' => 'Transportadora Dois',
            'valor_frete_custo_padrao' => 60,
            'valor_frete_cobrado_padrao' => 75,
        ]);
        $service = app(FreteService::class);

        $firstLink = $service->vincularTransportadora($cliente, $primeira, [
            'preferencial' => true,
            'modalidade_entrega_padrao' => ModalidadeEntrega::Transportadora,
            'valor_frete_custo_padrao' => 35,
            'valor_frete_cobrado_padrao' => 50,
        ]);
        $service->vincularTransportadora($cliente, $segunda, ['preferencial' => true]);

        $this->assertFalse($firstLink->fresh()->preferencial);
        $this->assertTrue($cliente->transportadoras()->whereKey($segunda->id)->exists());

        $quote = $service->cotar($cliente, $primeira, ModalidadeEntrega::Transportadora, [
            'valor_frete_cobrado' => 52,
        ]);

        $this->assertSame($primeira->id, $quote['transportadora_id']);
        $this->assertSame('transportadora', $quote['modalidade_entrega']);
        $this->assertSame(35.0, $quote['valor_frete_custo']);
        $this->assertSame(52.0, $quote['valor_frete_cobrado']);
        $this->assertSame(17.0, $quote['margem_frete']);
        $this->assertSame([
            'transportadora_id' => $primeira->id,
            'modalidade_entrega' => 'transportadora',
            'valor_frete_custo' => 35.0,
            'valor_frete_cobrado' => 52.0,
        ], $service->prepararPayloadRemessa(
            $cliente,
            $primeira,
            ModalidadeEntrega::Transportadora,
            ['valor_frete_cobrado' => 52],
        ));
    }
}
