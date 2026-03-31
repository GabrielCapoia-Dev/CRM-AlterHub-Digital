<?php

namespace Database\Seeders;

use App\Models\Categorias\CategoriaFornecimento;
use App\Models\Empresas\FormaPagamento;
use App\Models\Empresas\Fornecedor;
use App\Models\Empresas\PrazoPagamento;
use App\Models\Status\StatusHomologacao;
use Illuminate\Database\Seeder;

class FornecedorSeeder extends Seeder
{
    public function run(): void
    {
        // ── Categorias de fornecimento ────────────────────────────────────
        $categorias = collect([
            'Reagentes / insumos',
            'Equipamentos',
            'Serviços',
            'Logística',
            'Embalagens',
            'Tecnologia da informação',
            'Manutenção e calibração',
        ])->map(fn ($nome) => CategoriaFornecimento::firstOrCreate(['nome' => $nome]));

        // ── Status de homologação ─────────────────────────────────────────
        $statusList = collect([
            'Em homologação',
            'Homologado',
            'Pendência documental',
            'Suspenso',
            'Inativo',
        ])->map(fn ($nome) => StatusHomologacao::firstOrCreate(['nome' => $nome]));

        // ── Prazos de pagamento ───────────────────────────────────────────
        $prazos = collect([
            '15 dias',
            '30 dias',
            '45 dias',
            '60 dias',
            '90 dias',
            'À vista',
        ])->map(fn ($nome) => PrazoPagamento::firstOrCreate(['nome' => $nome]));

        // ── Formas de pagamento ───────────────────────────────────────────
        $formas = collect([
            'Boleto bancário',
            'Pix',
            'Transferência (TED / DOC)',
            'Cartão corporativo',
            'Cheque',
        ])->map(fn ($nome) => FormaPagamento::firstOrCreate(['nome' => $nome]));

        // ── Helpers de lookup ─────────────────────────────────────────────
        $categoria  = fn (string $nome) => $categorias->firstWhere('nome', $nome)->id;
        $status     = fn (string $nome) => $statusList->firstWhere('nome', $nome)->id;
        $prazo      = fn (string $nome) => $prazos->firstWhere('nome', $nome)->id;
        $forma      = fn (string $nome) => $formas->firstWhere('nome', $nome)->id;

        // ── Fornecedores ──────────────────────────────────────────────────
        $fornecedores = [
            [
                'codigo_interno'            => 'FOR-2024-101',
                'razao_social'              => 'ReagentBio Distribuidora Ltda.',
                'nome_fantasia'             => 'ReagentBio',
                'cnpj'                      => '10.111.222/0001-33',
                'inscricao_estadual'        => '116.888.888.112',
                'id_categoria_fornecimento' => $categoria('Reagentes / insumos'),
                'id_status_homologacao'     => $status('Homologado'),
                'id_prazo_pagamento'        => $prazo('30 dias'),
                'id_forma_pagamento'        => $forma('Boleto bancário'),
                'nome_completo'             => 'Fernanda Lopes',
                'cargo'                     => 'Executiva de contas',
                'email'                     => 'flopes@reagentbio.com.br',
                'telefone'                  => '(11) 98877-6600',
                'cep'                       => '04538-132',
                'uf'                        => 'SP',
                'logradouro'                => 'Rua Funchal',
                'numero'                    => '500',
                'complemento'               => '8º andar',
                'bairro'                    => 'Vila Olímpia',
                'cidade'                    => 'São Paulo',
                'observacoes'               => 'Homologação ISO 9001 anexa. Lead time médio reagentes: 5 dias úteis.',
            ],
            [
                'codigo_interno'            => 'FOR-2024-088',
                'razao_social'              => 'CryoEquip Indústria S.A.',
                'nome_fantasia'             => 'CryoEquip',
                'cnpj'                      => '20.222.333/0001-44',
                'inscricao_estadual'        => '283.444.555.116',
                'id_categoria_fornecimento' => $categoria('Equipamentos'),
                'id_status_homologacao'     => $status('Homologado'),
                'id_prazo_pagamento'        => $prazo('45 dias'),
                'id_forma_pagamento'        => $forma('Transferência (TED / DOC)'),
                'nome_completo'             => 'Roberto Dias',
                'cargo'                     => 'Pós-venda',
                'email'                     => 'rdias@cryoequip.com.br',
                'telefone'                  => '(19) 97766-5500',
                'cep'                       => '13087-000',
                'uf'                        => 'SP',
                'logradouro'                => 'Rodovia Anhanguera',
                'numero'                    => 'Km 104',
                'complemento'               => 'Galpão 2',
                'bairro'                    => 'Distrito Industrial',
                'cidade'                    => 'Valinhos',
                'observacoes'               => 'Manutenção preventiva contratada anualmente.',
            ],
            [
                'codigo_interno'            => 'FOR-2023-940',
                'razao_social'              => 'LabSteril Serviços Técnicos',
                'nome_fantasia'             => 'LabSteril',
                'cnpj'                      => '30.333.444/0001-55',
                'inscricao_estadual'        => 'Isento',
                'id_categoria_fornecimento' => $categoria('Serviços'),
                'id_status_homologacao'     => $status('Em homologação'),
                'id_prazo_pagamento'        => $prazo('30 dias'),
                'id_forma_pagamento'        => $forma('Pix'),
                'nome_completo'             => 'Camila Ribeiro',
                'cargo'                     => 'Coordenação',
                'email'                     => 'camila@labsteril.com.br',
                'telefone'                  => '(31) 96655-4400',
                'cep'                       => '30130-100',
                'uf'                        => 'MG',
                'logradouro'                => 'Av. Afonso Pena',
                'numero'                    => '1500',
                'complemento'               => null,
                'bairro'                    => 'Centro',
                'cidade'                    => 'Belo Horizonte',
                'observacoes'               => 'Documentação de biossegurança em análise.',
            ],
            [
                'codigo_interno'            => 'FOR-2023-902',
                'razao_social'              => 'ColdChain Logística Frigorificada',
                'nome_fantasia'             => 'ColdChain',
                'cnpj'                      => '40.444.555/0001-66',
                'inscricao_estadual'        => '096/3456789',
                'id_categoria_fornecimento' => $categoria('Logística'),
                'id_status_homologacao'     => $status('Homologado'),
                'id_prazo_pagamento'        => $prazo('60 dias'),
                'id_forma_pagamento'        => $forma('Boleto bancário'),
                'nome_completo'             => 'Eduardo Mattos',
                'cargo'                     => 'Operações',
                'email'                     => 'ops@coldchain.com.br',
                'telefone'                  => '(51) 95544-3300',
                'cep'                       => '90200-310',
                'uf'                        => 'RS',
                'logradouro'                => 'Av. Sertório',
                'numero'                    => '1800',
                'complemento'               => null,
                'bairro'                    => 'Jardim Botânico',
                'cidade'                    => 'Porto Alegre',
                'observacoes'               => 'Temperatura -20°C e -80°C homologadas.',
            ],
            [
                'codigo_interno'            => 'FOR-2023-875',
                'razao_social'              => 'MicroTube Plásticos Ltda.',
                'nome_fantasia'             => 'MicroTube',
                'cnpj'                      => '50.555.666/0001-77',
                'inscricao_estadual'        => '255.666.777.118',
                'id_categoria_fornecimento' => $categoria('Embalagens'),
                'id_status_homologacao'     => $status('Homologado'),
                'id_prazo_pagamento'        => $prazo('30 dias'),
                'id_forma_pagamento'        => $forma('Pix'),
                'nome_completo'             => 'Patrícia Nunes',
                'cargo'                     => 'Vendas B2B',
                'email'                     => 'pnunes@microtube.com.br',
                'telefone'                  => '(48) 94433-2200',
                'cep'                       => '88015-702',
                'uf'                        => 'SC',
                'logradouro'                => 'Rua Conselheiro Mafra',
                'numero'                    => '120',
                'complemento'               => null,
                'bairro'                    => 'Centro',
                'cidade'                    => 'Florianópolis',
                'observacoes'               => null,
            ],
            [
                'codigo_interno'            => 'FOR-2023-860',
                'razao_social'              => 'BioPack Soluções em Embalagem',
                'nome_fantasia'             => 'BioPack',
                'cnpj'                      => '60.666.777/0001-88',
                'inscricao_estadual'        => '412.777.888.119',
                'id_categoria_fornecimento' => $categoria('Embalagens'),
                'id_status_homologacao'     => $status('Pendência documental'),
                'id_prazo_pagamento'        => $prazo('45 dias'),
                'id_forma_pagamento'        => $forma('Boleto bancário'),
                'nome_completo'             => 'Gustavo Meyer',
                'cargo'                     => 'Fiscal',
                'email'                     => 'fiscal@biopack.com.br',
                'telefone'                  => '(41) 93322-1100',
                'cep'                       => '80250-104',
                'uf'                        => 'PR',
                'logradouro'                => 'Av. República Argentina',
                'numero'                    => '2000',
                'complemento'               => null,
                'bairro'                    => 'Água Verde',
                'cidade'                    => 'Curitiba',
                'observacoes'               => 'Aguardando certificado de rastreabilidade lote 2024.',
            ],
            [
                'codigo_interno'            => 'FOR-2024-112',
                'razao_social'              => 'Analítica Service & Calibração',
                'nome_fantasia'             => 'Analítica',
                'cnpj'                      => '70.777.888/0001-99',
                'inscricao_estadual'        => '789.888.999.220',
                'id_categoria_fornecimento' => $categoria('Manutenção e calibração'),
                'id_status_homologacao'     => $status('Em homologação'),
                'id_prazo_pagamento'        => $prazo('90 dias'),
                'id_forma_pagamento'        => $forma('Transferência (TED / DOC)'),
                'nome_completo'             => 'Helena Prado',
                'cargo'                     => 'Qualidade',
                'email'                     => 'qualidade@analitica.srv.br',
                'telefone'                  => '(21) 92211-0099',
                'cep'                       => '20031-170',
                'uf'                        => 'RJ',
                'logradouro'                => 'Av. Graça Aranha',
                'numero'                    => '1',
                'complemento'               => 'Sala 801',
                'bairro'                    => 'Centro',
                'cidade'                    => 'Rio de Janeiro',
                'observacoes'               => 'Calibração RBC agendada para abril.',
            ],
            [
                'codigo_interno'            => 'FOR-2024-120',
                'razao_social'              => 'Vetorial Insumos Biotecnologia',
                'nome_fantasia'             => 'Vetorial',
                'cnpj'                      => '80.888.999/0001-00',
                'inscricao_estadual'        => '334.999.000.221',
                'id_categoria_fornecimento' => $categoria('Reagentes / insumos'),
                'id_status_homologacao'     => $status('Suspenso'),
                'id_prazo_pagamento'        => $prazo('30 dias'),
                'id_forma_pagamento'        => $forma('Boleto bancário'),
                'nome_completo'             => 'Marcos Veiga',
                'cargo'                     => 'Comercial',
                'email'                     => 'mveiga@vetorial.bio',
                'telefone'                  => '(11) 91100-8877',
                'cep'                       => '05407-002',
                'uf'                        => 'SP',
                'logradouro'                => 'Rua Butantã',
                'numero'                    => '350',
                'complemento'               => null,
                'bairro'                    => 'Pinheiros',
                'cidade'                    => 'São Paulo',
                'observacoes'               => 'Suspenso por não conformidade em auditoria 02/2026 — reavaliação em 90 dias.',
            ],
            [
                'codigo_interno'            => 'FOR-2024-135',
                'razao_social'              => 'DataLab Tecnologia Ltda.',
                'nome_fantasia'             => 'DataLab',
                'cnpj'                      => '91.000.111/0001-22',
                'inscricao_estadual'        => 'Isento',
                'id_categoria_fornecimento' => $categoria('Tecnologia da informação'),
                'id_status_homologacao'     => $status('Homologado'),
                'id_prazo_pagamento'        => $prazo('30 dias'),
                'id_forma_pagamento'        => $forma('Cartão corporativo'),
                'nome_completo'             => 'Aline Souza',
                'cargo'                     => 'Account Manager',
                'email'                     => 'aline@datalab.com.br',
                'telefone'                  => '(11) 93344-5566',
                'cep'                       => '01310-100',
                'uf'                        => 'SP',
                'logradouro'                => 'Av. Paulista',
                'numero'                    => '1000',
                'complemento'               => 'Conjunto 142',
                'bairro'                    => 'Bela Vista',
                'cidade'                    => 'São Paulo',
                'observacoes'               => 'Contrato SaaS anual renovado em janeiro/2026.',
            ],
        ];

        foreach ($fornecedores as $dados) {
            Fornecedor::firstOrCreate(
                ['cnpj' => $dados['cnpj']],
                $dados
            );
        }

        $this->command->info('✅ FornecedorSeeder concluído — ' . count($fornecedores) . ' fornecedores cadastrados.');
    }
}