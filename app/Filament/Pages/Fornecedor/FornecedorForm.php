<?php

namespace App\Filament\Pages\Fornecedor;

use App\Models\Categorias\CategoriaFornecimento;
use App\Models\Empresas\FormaPagamento;
use App\Models\Empresas\Fornecedor;
use App\Models\Empresas\PrazoPagamento;
use App\Models\Status\StatusHomologacao;
use App\Services\Empresas\FornecedorService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;

/**
 * Componente Livewire: Modal de criação/edição de Fornecedor
 *
 * Uso na view pai (fornecedor-list.blade.php):
 *   @livewire('filament.pages.fornecedor.fornecedor-form')
 *
 * Escuta o evento Alpine/Livewire "open-fornecedor-modal" com payload { uuid }.
 * Despacha "fornecedor-saved" para a listagem recarregar.
 */
class FornecedorForm extends Page
{
    // ─────────────────────────────────────────────────────────────────────────
    // Config Filament (não é uma página autônoma, apenas o componente Livewire)
    // ─────────────────────────────────────────────────────────────────────────

    protected static bool   $shouldRegisterNavigation = false;
    protected string $view = 'filament.pages.fornecedor-form';

    // ─────────────────────────────────────────────────────────────────────────
    // Estado do modal
    // ─────────────────────────────────────────────────────────────────────────

    /** Controla visibilidade do overlay */
    public bool $open = false;

    /** UUID do fornecedor em edição; null = novo cadastro */
    public ?string $uuid = null;

    /** Array de campos do formulário */
    public array $form = [
        'codigo_interno'           => '',
        'razao_social'             => '',
        'nome_fantasia'            => '',
        'cnpj'                     => '',
        'inscricao_estadual'       => '',
        'id_categoria_fornecimento'=> '',
        'id_status_homologacao'    => '',
        'id_prazo_pagamento'       => '',
        'id_forma_pagamento'       => '',
        'nome_completo'            => '',
        'cargo'                    => '',
        'email'                    => '',
        'telefone'                 => '',
        'cep'                      => '',
        'uf'                       => '',
        'logradouro'               => '',
        'numero'                   => '',
        'complemento'              => '',
        'bairro'                   => '',
        'cidade'                   => '',
        'observacoes'              => '',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Dados estáticos para os selects
    // ─────────────────────────────────────────────────────────────────────────

    public Collection $categorias;
    public Collection $statusList;
    public Collection $prazos;
    public Collection $formas;

    // ─────────────────────────────────────────────────────────────────────────
    // Boot / Mount
    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->carregarSelects();
    }

    private function carregarSelects(): void
    {
        $this->categorias = CategoriaFornecimento::orderBy('nome')->get();
        $this->statusList = StatusHomologacao::orderBy('nome')->get();
        $this->prazos     = PrazoPagamento::orderBy('dias')->get();
        $this->formas     = FormaPagamento::orderBy('nome')->get();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Listeners
    // ─────────────────────────────────────────────────────────────────────────

    protected $listeners = [
        'open-fornecedor-modal' => 'abrir',
        'confirm-delete-confirmed' => 'excluir',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Abrir / Fechar
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Abre o modal. Se uuid for passado, carrega o fornecedor para edição.
     */
    public function abrir(?string $uuid = null): void
    {
        $this->resetValidation();
        $this->uuid = $uuid;

        if ($uuid) {
            $fornecedor = app(FornecedorService::class)->encontrar($uuid);
            $this->form = array_merge($this->form, $fornecedor->only(array_keys($this->form)));
        } else {
            $this->form = array_fill_keys(array_keys($this->form), '');
        }

        $this->open = true;
    }

    /** Fecha e limpa o estado. */
    public function fechar(): void
    {
        $this->open = false;
        $this->uuid = null;
        $this->resetValidation();
        $this->form = array_fill_keys(array_keys($this->form), '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validação
    // ─────────────────────────────────────────────────────────────────────────

    protected function rules(): array
    {
        $cnpjUnique = Rule::unique('fornecedores', 'cnpj');
        if ($this->uuid) {
            $cnpjUnique = $cnpjUnique->ignore($this->uuid, 'uuid');
        }

        return [
            'form.razao_social'              => ['required', 'string', 'max:255'],
            'form.nome_fantasia'             => ['nullable', 'string', 'max:255'],
            'form.cnpj'                      => ['required', 'string', 'max:18', $cnpjUnique],
            'form.inscricao_estadual'        => ['nullable', 'string', 'max:50'],
            'form.id_categoria_fornecimento' => ['required', 'exists:categorias_fornecimento,id'],
            'form.id_status_homologacao'     => ['nullable', 'exists:status_homologacao,id'],
            'form.id_prazo_pagamento'        => ['required', 'exists:prazos_pagamento,id'],
            'form.id_forma_pagamento'        => ['required', 'exists:formas_pagamento,id'],
            'form.nome_completo'             => ['required', 'string', 'max:255'],
            'form.cargo'                     => ['nullable', 'string', 'max:100'],
            'form.email'                     => ['required', 'email', 'max:255'],
            'form.telefone'                  => ['required', 'string', 'max:20'],
            'form.cep'                       => ['nullable', 'string', 'max:10'],
            'form.uf'                        => ['nullable', 'string', 'size:2'],
            'form.logradouro'                => ['nullable', 'string', 'max:255'],
            'form.numero'                    => ['nullable', 'string', 'max:20'],
            'form.complemento'               => ['nullable', 'string', 'max:100'],
            'form.bairro'                    => ['nullable', 'string', 'max:100'],
            'form.cidade'                    => ['nullable', 'string', 'max:100'],
            'form.observacoes'               => ['nullable', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.razao_social.required'              => 'A razão social é obrigatória.',
            'form.cnpj.required'                      => 'O CNPJ é obrigatório.',
            'form.cnpj.unique'                        => 'Este CNPJ já está cadastrado.',
            'form.id_categoria_fornecimento.required' => 'Selecione a categoria de fornecimento.',
            'form.id_prazo_pagamento.required'        => 'Selecione o prazo de pagamento.',
            'form.id_forma_pagamento.required'        => 'Selecione a forma de pagamento.',
            'form.nome_completo.required'             => 'Informe o nome do contato.',
            'form.email.required'                     => 'O e-mail é obrigatório.',
            'form.email.email'                        => 'Informe um e-mail válido.',
            'form.telefone.required'                  => 'O telefone é obrigatório.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Salvar
    // ─────────────────────────────────────────────────────────────────────────

    public function salvar(): void
    {
        $dados = $this->validate();
        $dados = $dados['form']; // extrai do namespace "form.*"

        // Converte campos vazios para null
        $dados = array_map(fn ($v) => $v === '' ? null : $v, $dados);

        $service = app(FornecedorService::class);

        if ($this->uuid) {
            $service->atualizar($this->uuid, $dados);
            $msg = 'Fornecedor atualizado com sucesso.';
        } else {
            $service->criar($dados);
            $msg = 'Fornecedor cadastrado com sucesso.';
        }

        $this->fechar();

        // Notifica a listagem para recarregar
        $this->dispatch('fornecedor-saved');

        // Notificação toast (compatível com Filament Notifications)
        $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Exclusão
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dispara evento para o modal de confirmação antes de deletar.
     */
    public function confirmarExclusao(): void
    {
        $this->dispatch('confirm-delete-fornecedor', uuid: $this->uuid);
    }

    /**
     * Executado quando o modal de confirmação aprova a exclusão.
     */
    public function excluir(string $uuid): void
    {
        app(FornecedorService::class)->deletar($uuid);

        $this->fechar();
        $this->dispatch('fornecedor-saved');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Fornecedor excluído.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Integração ViaCEP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Consulta a API ViaCEP e preenche os campos de endereço automaticamente.
     */
    public function buscarCep(string $cep): void
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            return;
        }

        try {
            $resp = Http::timeout(4)->get("https://viacep.com.br/ws/{$cep}/json/");

            if ($resp->failed() || isset($resp['erro'])) {
                return;
            }

            $data = $resp->json();

            $this->form['logradouro'] = $data['logradouro'] ?? $this->form['logradouro'];
            $this->form['bairro']     = $data['bairro']     ?? $this->form['bairro'];
            $this->form['cidade']     = $data['localidade'] ?? $this->form['cidade'];
            $this->form['uf']         = $data['uf']         ?? $this->form['uf'];

        } catch (\Throwable) {
            // Silencioso: falha da API não deve travar o formulário
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dados passados para a view
    // ─────────────────────────────────────────────────────────────────────────

    protected function getViewData(): array
    {
        return [
            'categorias' => $this->categorias,
            'statusList' => $this->statusList,
            'prazos'     => $this->prazos,
            'formas'     => $this->formas,
        ];
    }
}