<?php

namespace Tests\Feature\Fiscal;

use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Status\StatusCliente;
use App\Rules\FlexibleTaxIdentifierRule;
use App\Rules\UniqueNormalizedTaxIdentifierRule;
use App\Support\Fiscal\TaxIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TaxIdentifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_and_validates_brazilian_cnpj(): void
    {
        $this->assertTrue(TaxIdentifier::isValid('45.723.174/0001-10'));
        $this->assertTrue(TaxIdentifier::isValid('45723174000110'));
        $this->assertSame('45.723.174/0001-10', TaxIdentifier::normalizeForStorage('45723174000110'));
        $this->assertFalse(TaxIdentifier::isValid('11.111.111/1111-11'));
    }

    public function test_it_accepts_international_identifiers_and_supports_normalized_uniqueness(): void
    {
        $validator = Validator::make(
            ['documento' => 'vat-de 123/456-789'],
            ['documento' => [new FlexibleTaxIdentifierRule()]],
        );

        $this->assertTrue($validator->passes());

        $segmento = CategoriaSegmento::query()->create(['nome' => 'Hospital']);
        $status = StatusCliente::query()->create(['nome' => 'Ativo']);

        $cliente = Cliente::query()->create([
            'razao_social' => 'Cliente Internacional',
            'cnpj' => 'vat-de 123/456-789',
            'id_categoria_segmento' => $segmento->id,
            'id_status_cliente' => $status->id,
        ]);

        $lookup = Cliente::query()
            ->lookupByCodigoOuCnpj('VATDE123456789')
            ->first();

        $this->assertNotNull($lookup);
        $this->assertSame($cliente->id, $lookup?->id);

        $duplicateValidator = Validator::make(
            ['documento' => 'VAT DE 123 456 789'],
            ['documento' => [new UniqueNormalizedTaxIdentifierRule('clientes', 'cnpj')]],
        );

        $this->assertTrue($duplicateValidator->fails());
        $this->assertSame(
            ['Este documento fiscal ja esta cadastrado.'],
            $duplicateValidator->errors()->get('documento'),
        );
    }
}
