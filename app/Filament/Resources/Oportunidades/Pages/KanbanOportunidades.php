<?php

namespace App\Filament\Resources\Oportunidades\Pages;

use App\Enum\PermissoesEnum;
use App\Filament\Resources\Oportunidades\OportunidadeResource;
use App\Filament\Resources\VendasOperacao\VendaOperacaoResource;
use App\Rules\FlexibleTaxIdentifierRule;
use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\OportunidadeInteracao;
use App\Models\OportunidadeProduto;
use App\Models\OportunidadeTarefa;
use App\Models\Produto;
use App\Models\Status\StatusCliente;
use App\Models\VendaOperacao;
use App\Services\CRM\OportunidadeClienteService;
use App\Services\CRM\OportunidadeVendaService;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KanbanOportunidades extends Page
{
    protected static string $resource = OportunidadeResource::class;

    protected static ?string $title = 'CRM - Oportunidades';

    protected string $view = 'filament.resources.oportunidades.pages.kanban-oportunidades';

    protected Width|string|null $maxWidth = Width::Full;

    public string $search = '';

    public string $temperatureFilter = '';

    public string $lastInteractionDays = '';

    public ?int $ownerFilter = null;

    public ?int $segmentFilter = null;

    public string $viewMode = 'list';

    public bool $drawerOpen = false;

    public string $drawerMode = 'create';

    public string $activeDrawerTab = 'summary';

    public ?int $selectedOpportunityId = null;

    public bool $closingReasonModalOpen = false;

    public ?int $pendingMoveOpportunityId = null;

    public ?int $pendingMoveStageId = null;

    public string $pendingMoveReason = '';

    public bool $saleConfirmationModalOpen = false;

    public bool $discountApprovalModalOpen = false;

    /**
     * @var array<string, mixed>
     */
    public array $salePreview = [];

    /**
     * @var array<string, mixed>
     */
    public array $discountApprovalPreview = [];

    /**
     * @var array<string, mixed>
     */
    public array $pendingProductPayload = [];

    public ?int $pendingProductEditingId = null;

    /**
     * @var array<string, mixed>
     */
    public array $opportunityForm = [];

    /**
     * @var array<string, mixed>
     */
    public array $productForm = [];

    /**
     * @var array<string, mixed>
     */
    public array $interactionForm = [];

    /**
     * @var array<string, mixed>
     */
    public array $taskForm = [];

    public function mount(): void
    {
        $this->fillOpportunityForm();
        $this->resetProductForm();
        $this->resetInteractionForm();
        $this->resetTaskForm();
        $this->closeSaleConfirmation();
        $this->closeDiscountApproval();
    }

    public function updated(string $name, mixed $value): void
    {
        if (($name === 'opportunityForm.etapa_id') && (! $this->selectedStageIsClosing())) {
            $this->opportunityForm['motivo_fechamento'] = '';
        }

        if (($name === 'opportunityForm.client_lookup') && (($this->opportunityForm['client_mode'] ?? null) !== OportunidadeClienteService::MODE_NEW)) {
            $this->opportunityForm['cliente_id'] = null;
            $this->opportunityForm['client_lookup_status'] = '';
            $this->opportunityForm['client_lookup_message'] = blank($value)
                ? ''
                : 'Clique em Buscar cliente para localizar um cadastro existente.';
        }

        if ($name === 'segmentFilter') {
            $this->segmentFilter = blank($value) ? null : (int) $value;
        }

        if ($name === 'ownerFilter') {
            $this->ownerFilter = blank($value) ? null : (int) $value;
        }

        if ($name === 'productForm.produto_id') {
            $this->fillProductPricingFromSelectedProduct();
        }

        if ($name === 'productForm.desconto_percentual') {
            $this->refreshProductNegotiatedPrice();
        }

        if ($name === 'viewMode') {
            $this->setViewMode((string) $value);
        }
    }

    public function getListUrl(): string
    {
        return static::getResource()::getUrl('list');
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode === 'kanban' ? 'kanban' : 'list';
    }

    public function getSelectedOpportunity(): ?Oportunidade
    {
        if (! $this->selectedOpportunityId) {
            return null;
        }

        return Oportunidade::query()
            ->with([
                'cliente.categoriaSegmento',
                'user',
                'etapa',
                'oportunidadeProdutos' => fn ($query) => $query
                    ->with(['produto', 'descontoAprovadoPor'])
                    ->latest('updated_at'),
                'oportunidadeInteracoes' => fn ($query) => $query
                    ->with('user')
                    ->latest('ocorreu_em'),
                'oportunidadeTarefas' => fn ($query) => $query
                    ->with('user')
                    ->orderBy('data_prevista')
                    ->latest('updated_at'),
                'oportunidadeMovimentacoes' => fn ($query) => $query
                    ->with(['user', 'etapaOrigem', 'etapaDestino'])
                    ->latest('movido_em'),
                'vendaOperacaoPedido.vendasOperacao.produto',
            ])
            ->find($this->selectedOpportunityId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBoardColumns(): array
    {
        $etapas = collect($this->getStageMetasById())->values();

        /** @var EloquentCollection<int, Oportunidade> $oportunidades */
        $oportunidades = $this->getBoardQuery()->get();
        $oportunidadesPorEtapa = $oportunidades->groupBy('etapa_id');

        return $etapas
            ->values()
            ->map(function (array $etapa) use ($oportunidadesPorEtapa): array {
                /** @var EloquentCollection<int, Oportunidade> $cards */
                $cards = $oportunidadesPorEtapa->get($etapa['id'], new EloquentCollection);
                $soma = (float) $cards->sum(fn (Oportunidade $oportunidade): float => $oportunidade->calcularValorEstimado());

                return [
                    'id' => $etapa['id'],
                    'nome' => $etapa['nome'],
                    'slug' => $etapa['slug'],
                    'cor' => $etapa['cor'],
                    'fechamento' => $etapa['fechamento'],
                    'count' => $cards->count(),
                    'sum' => $soma,
                    'sum_formatted' => $this->formatMoney($soma),
                    'cards' => $cards
                        ->map(fn (Oportunidade $oportunidade): array => $this->serializeOpportunityCard($oportunidade, $etapa))
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getListRows(): array
    {
        $stages = $this->getStageMetasById();

        /** @var EloquentCollection<int, Oportunidade> $oportunidades */
        $oportunidades = $this->getBoardQuery()->get();

        return $oportunidades
            ->map(fn (Oportunidade $oportunidade): array => $this->serializeOpportunityCard(
                $oportunidade,
                $stages[$oportunidade->etapa_id] ?? null,
            ))
            ->all();
    }

    /**
     * @return array<int, array{id:int,nome:string}>
     */
    public function getStageOptions(): array
    {
        return Etapa::query()
            ->orderBy('ordem')
            ->orderBy('id')
            ->get(['id', 'nome'])
            ->map(fn (Etapa $etapa): array => [
                'id' => $etapa->id,
                'nome' => $etapa->nome,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int,nome:string}>
     */
    public function getOwnerOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'nome' => $user->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int,nome:string,codigo:?string,unidade:?string,preco_tabela:?float,preco_minimo:?float}>
     */
    public function getProductOptions(): array
    {
        return Produto::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'codigo_interno', 'nome', 'unidade_medida', 'preco_tabela', 'preco_minimo'])
            ->map(fn (Produto $produto): array => [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'codigo' => $produto->codigo_interno,
                'unidade' => $produto->unidade_medida,
                'preco_tabela' => $produto->preco_tabela !== null ? (float) $produto->preco_tabela : null,
                'preco_minimo' => $produto->preco_minimo !== null ? (float) $produto->preco_minimo : null,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int,nome:string}>
     */
    public function getSegmentOptions(): array
    {
        return CategoriaSegmento::query()
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (CategoriaSegmento $segmento): array => [
                'id' => $segmento->id,
                'nome' => $segmento->nome,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int,nome:string}>
     */
    public function getClientStatusOptions(): array
    {
        return StatusCliente::query()
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (StatusCliente $status): array => [
                'id' => $status->id,
                'nome' => $status->nome,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function getUfOptions(): array
    {
        return [
            'AC' => 'AC',
            'AL' => 'AL',
            'AM' => 'AM',
            'AP' => 'AP',
            'BA' => 'BA',
            'CE' => 'CE',
            'DF' => 'DF',
            'ES' => 'ES',
            'GO' => 'GO',
            'MA' => 'MA',
            'MG' => 'MG',
            'MS' => 'MS',
            'MT' => 'MT',
            'PA' => 'PA',
            'PB' => 'PB',
            'PE' => 'PE',
            'PI' => 'PI',
            'PR' => 'PR',
            'RJ' => 'RJ',
            'RN' => 'RN',
            'RO' => 'RO',
            'RR' => 'RR',
            'RS' => 'RS',
            'SC' => 'SC',
            'SE' => 'SE',
            'SP' => 'SP',
            'TO' => 'TO',
        ];
    }

    public function getSelectedClient(): ?Cliente
    {
        $clienteId = (int) ($this->opportunityForm['cliente_id'] ?? 0);

        if (! $clienteId) {
            return null;
        }

        return Cliente::query()
            ->with(['categoriaSegmento', 'statusCliente'])
            ->find($clienteId);
    }

    public function selectedStageIsClosing(): bool
    {
        $etapaId = (int) ($this->opportunityForm['etapa_id'] ?? 0);

        if (! $etapaId) {
            return false;
        }

        return (bool) Etapa::query()
            ->whereKey($etapaId)
            ->value('fechamento');
    }

    public function setSegmentFilter(?int $segmentId = null): void
    {
        $this->segmentFilter = $segmentId;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->temperatureFilter = '';
        $this->lastInteractionDays = '';
        $this->ownerFilter = null;
        $this->segmentFilter = null;
    }

    public function openDrawer(int $opportunityId): void
    {
        $oportunidade = $this->findOpportunityOrFail($opportunityId);

        Gate::authorize('view', $oportunidade);

        $this->drawerOpen = true;
        $this->drawerMode = 'edit';
        $this->activeDrawerTab = 'summary';
        $this->selectedOpportunityId = $oportunidade->id;

        $this->fillOpportunityForm($oportunidade);
        $this->resetProductForm();
        $this->resetInteractionForm();
        $this->resetTaskForm();
    }

    public function openCreateDrawer(?int $stageId = null): void
    {
        Gate::authorize('create', Oportunidade::class);

        $this->drawerOpen = true;
        $this->drawerMode = 'create';
        $this->activeDrawerTab = 'summary';
        $this->selectedOpportunityId = null;

        $this->fillOpportunityForm(
            opportunity: null,
            stageId: $stageId ?: $this->resolveDefaultStageId(),
        );

        $this->resetProductForm();
        $this->resetInteractionForm();
        $this->resetTaskForm();
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->drawerMode = 'create';
        $this->activeDrawerTab = 'summary';
        $this->selectedOpportunityId = null;

        $this->fillOpportunityForm();
        $this->resetProductForm();
        $this->resetInteractionForm();
        $this->resetTaskForm();
    }

    public function searchClient(): void
    {
        $cliente = app(OportunidadeClienteService::class)->findByLookup($this->opportunityForm['client_lookup'] ?? null);

        if (! $cliente) {
            $this->opportunityForm['cliente_id'] = null;
            $this->opportunityForm['client_lookup_status'] = 'missing';
            $this->opportunityForm['client_lookup_message'] = 'Nenhum cliente foi encontrado. Use Novo Cliente para cadastrar no mesmo fluxo.';

            Notification::make()
                ->title('Cliente nao encontrado')
                ->body('Voce pode seguir com Novo Cliente e concluir a oportunidade no mesmo lugar.')
                ->warning()
                ->send();

            return;
        }

        $this->applyClientFormState(app(OportunidadeClienteService::class)->existingClientFormState($cliente));

        Notification::make()
            ->title('Cliente encontrado')
            ->body("{$cliente->razao_social} foi vinculado a oportunidade.")
            ->success()
            ->send();
    }

    public function activateNewClientForm(): void
    {
        $this->applyClientFormState(app(OportunidadeClienteService::class)->newClientFormState());
    }

    public function useExistingClientSearch(): void
    {
        $this->applyClientFormState(app(OportunidadeClienteService::class)->blankFormState());
    }

    public function saveOpportunity(): void
    {
        $selected = $this->getSelectedOpportunity();

        if ($selected) {
            Gate::authorize('update', $selected);
        } else {
            Gate::authorize('create', Oportunidade::class);
        }

        $validated = $this->validate($this->opportunityRules(), [], [
            'opportunityForm.titulo' => 'titulo',
            'opportunityForm.client_lookup' => 'busca do cliente',
            'opportunityForm.client_razao_social' => 'razao social',
            'opportunityForm.client_nome_fantasia' => 'nome fantasia',
            'opportunityForm.client_cnpj' => 'documento fiscal',
            'opportunityForm.client_segmento_id' => 'segmento do cliente',
            'opportunityForm.client_status_id' => 'status do cliente',
            'opportunityForm.client_nome_completo' => 'contato principal',
            'opportunityForm.client_cargo' => 'cargo',
            'opportunityForm.client_email' => 'e-mail',
            'opportunityForm.client_telefone' => 'telefone',
            'opportunityForm.client_cidade' => 'cidade',
            'opportunityForm.client_uf' => 'UF',
            'opportunityForm.client_observacao' => 'observacoes do cliente',
            'opportunityForm.etapa_id' => 'etapa',
            'opportunityForm.user_id' => 'responsavel',
            'opportunityForm.temperatura' => 'temperatura',
            'opportunityForm.valor_estimado' => 'valor estimado',
            'opportunityForm.motivo_fechamento' => 'motivo de fechamento',
            'opportunityForm.notas' => 'notas',
        ]);

        try {
            $payload = app(OportunidadeClienteService::class)->prepareOpportunityData($validated['opportunityForm']);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => ["opportunityForm.{$field}" => $messages])
                    ->all(),
            );
        }

        $oportunidade = $selected ?? new Oportunidade;
        $payload['valor_estimado'] = $selected?->calcularValorEstimado() ?: null;
        $oportunidade->fill($payload);
        $oportunidade->save();

        $this->selectedOpportunityId = $oportunidade->id;
        $this->drawerMode = 'edit';
        $this->fillOpportunityForm($oportunidade->fresh());

        Notification::make()
            ->title($selected ? 'Oportunidade atualizada' : 'Oportunidade criada')
            ->success()
            ->send();
    }

    public function handleStageDrop(int $opportunityId, int $stageId): void
    {
        $this->moveOpportunity($opportunityId, $stageId);
    }

    public function moveOpportunity(int $opportunityId, int $stageId, ?string $reason = null): void
    {
        $oportunidade = $this->findOpportunityOrFail($opportunityId);
        $etapaDestino = Etapa::query()->findOrFail($stageId);

        Gate::authorize('update', $oportunidade);

        if ($oportunidade->etapa_id === $etapaDestino->id) {
            return;
        }

        $reason = blank($reason) ? null : trim($reason);

        if ($etapaDestino->fechamento && blank($reason)) {
            $this->pendingMoveOpportunityId = $oportunidade->id;
            $this->pendingMoveStageId = $etapaDestino->id;
            $this->pendingMoveReason = '';
            $this->closingReasonModalOpen = true;

            return;
        }

        $oportunidade->update([
            'etapa_id' => $etapaDestino->id,
            'motivo_fechamento' => $reason,
        ]);

        if ($this->selectedOpportunityId === $oportunidade->id) {
            $this->fillOpportunityForm($oportunidade->fresh());
        }

        $this->cancelPendingMove();

        Notification::make()
            ->title('Etapa atualizada')
            ->body("A oportunidade foi movida para {$etapaDestino->nome}.")
            ->success()
            ->send();
    }

    public function confirmPendingStageMove(): void
    {
        $validated = $this->validate([
            'pendingMoveOpportunityId' => ['required', 'integer', 'exists:oportunidades,id'],
            'pendingMoveStageId' => ['required', 'integer', 'exists:etapas,id'],
            'pendingMoveReason' => ['required', 'string'],
        ], [], [
            'pendingMoveReason' => 'motivo de fechamento',
        ]);

        $this->moveOpportunity(
            (int) $validated['pendingMoveOpportunityId'],
            (int) $validated['pendingMoveStageId'],
            (string) $validated['pendingMoveReason'],
        );
    }

    public function cancelPendingMove(): void
    {
        $this->closingReasonModalOpen = false;
        $this->pendingMoveOpportunityId = null;
        $this->pendingMoveStageId = null;
        $this->pendingMoveReason = '';
    }

    public function openSaleConfirmation(?int $opportunityId = null): void
    {
        if ($opportunityId) {
            $this->selectedOpportunityId = $opportunityId;
        }

        $oportunidade = $opportunityId
            ? $this->findOpportunityOrFail($opportunityId)
            : $this->getSelectedOpportunity();

        abort_unless($oportunidade, 404);

        Gate::authorize('update', $oportunidade);
        Gate::authorize('create', VendaOperacao::class);

        $this->drawerOpen = false;
        $this->salePreview = app(OportunidadeVendaService::class)->preview($oportunidade);
        $this->saleConfirmationModalOpen = true;
    }

    public function closeSaleConfirmation(): void
    {
        $this->saleConfirmationModalOpen = false;
        $this->salePreview = [];
    }

    public function confirmOpportunitySale(): void
    {
        $oportunidade = $this->getSelectedOpportunity();

        abort_unless($oportunidade, 404);

        Gate::authorize('update', $oportunidade);
        Gate::authorize('create', VendaOperacao::class);

        try {
            $pedido = app(OportunidadeVendaService::class)->convert($oportunidade, Auth::user());
        } catch (ValidationException $exception) {
            $this->salePreview = app(OportunidadeVendaService::class)->preview($oportunidade);

            Notification::make()
                ->title('Venda nao confirmada')
                ->body(collect($exception->errors())->flatten()->take(4)->implode(' '))
                ->danger()
                ->send();

            return;
        }

        $this->closeSaleConfirmation();

        Notification::make()
            ->title('Venda confirmada')
            ->body("A venda {$pedido->codigo} foi registrada e o estoque foi baixado.")
            ->success()
            ->send();

        $this->redirect(VendaOperacaoResource::getUrl('index'), navigate: true);
    }

    public function saveProductLink(): void
    {
        $oportunidade = $this->getSelectedOpportunityForEditing();

        $editing = $this->productForm['id'] ? OportunidadeProduto::query()->findOrFail((int) $this->productForm['id']) : null;

        if ($editing) {
            Gate::authorize('update', $editing);
        } else {
            Gate::authorize('create', OportunidadeProduto::class);
        }

        $validated = $this->validate($this->productRules());

        $payload = $this->buildProductPayload($validated['productForm'], $oportunidade->id);
        $approvalPreview = $this->buildDiscountApprovalPreview($payload);

        if ($approvalPreview['required'] && ! $this->discountApprovalStillValid($editing, $payload)) {
            if (! $this->currentUserCanApproveDiscount()) {
                Notification::make()
                    ->title('Desconto precisa de aprovacao')
                    ->body('O preco final ficou abaixo do minimo do produto. Um responsavel com a permissao Aprovar Desconto deve confirmar esta negociacao.')
                    ->danger()
                    ->send();

                return;
            }

            $this->pendingProductPayload = $payload;
            $this->pendingProductEditingId = $editing?->id;
            $this->discountApprovalPreview = $approvalPreview;
            $this->discountApprovalModalOpen = true;

            return;
        }

        $this->persistProductLink($payload, $editing, preserveApproval: $approvalPreview['required']);
    }

    public function confirmDiscountApproval(): void
    {
        if (! $this->currentUserCanApproveDiscount()) {
            Notification::make()
                ->title('Permissao insuficiente')
                ->body('Apenas usuarios com a permissao Aprovar Desconto podem confirmar este desconto.')
                ->danger()
                ->send();

            return;
        }

        $oportunidade = $this->getSelectedOpportunityForEditing();
        $editing = $this->pendingProductEditingId
            ? OportunidadeProduto::query()->findOrFail($this->pendingProductEditingId)
            : null;

        if ($editing) {
            Gate::authorize('update', $editing);
            $this->ensureBelongsToSelectedOpportunity($editing->oportunidade_id);
        } else {
            Gate::authorize('create', OportunidadeProduto::class);
        }

        $payload = [
            ...$this->pendingProductPayload,
            'oportunidade_id' => $oportunidade->id,
        ];

        $this->persistProductLink($payload, $editing, approveDiscount: true);
        $this->closeDiscountApproval();
    }

    public function closeDiscountApproval(): void
    {
        $this->discountApprovalModalOpen = false;
        $this->discountApprovalPreview = [];
        $this->pendingProductPayload = [];
        $this->pendingProductEditingId = null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildProductPayload(array $data, int $opportunityId): array
    {
        return [
            'oportunidade_id' => $opportunityId,
            'produto_id' => (int) $data['produto_id'],
            'quantidade' => (float) $data['quantidade'],
            'preco_negociado' => blank($data['preco_negociado']) ? null : round((float) $data['preco_negociado'], 2),
            'desconto_percentual' => blank($data['desconto_percentual']) ? 0 : round((float) $data['desconto_percentual'], 2),
            'observacao' => blank($data['observacao']) ? null : trim((string) $data['observacao']),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persistProductLink(
        array $payload,
        ?OportunidadeProduto $editing,
        bool $approveDiscount = false,
        bool $preserveApproval = false,
    ): void {
        if ($approveDiscount) {
            $payload['desconto_aprovado_por'] = Auth::id();
            $payload['desconto_aprovado_em'] = now();
        } elseif (! $preserveApproval) {
            $payload['desconto_aprovado_por'] = null;
            $payload['desconto_aprovado_em'] = null;
        }

        $registro = $editing ?? new OportunidadeProduto;
        $registro->fill($payload);
        $registro->save();

        $this->resetProductForm();

        Notification::make()
            ->title($editing ? 'Produto atualizado' : 'Produto vinculado')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildDiscountApprovalPreview(array $payload): array
    {
        $produto = Produto::query()->find($payload['produto_id'] ?? null);
        $precoMinimo = $produto?->preco_minimo !== null ? (float) $produto->preco_minimo : 0.0;
        $precoFinal = $payload['preco_negociado'] !== null ? (float) $payload['preco_negociado'] : 0.0;
        $required = $produto && $precoMinimo > 0 && $precoFinal > 0 && $precoFinal < $precoMinimo;

        return [
            'required' => $required,
            'produto_nome' => $produto?->nome ?? 'Produto',
            'preco_tabela' => $produto?->preco_tabela !== null ? (float) $produto->preco_tabela : null,
            'preco_minimo' => $precoMinimo,
            'preco_final' => $precoFinal,
            'desconto_percentual' => (float) ($payload['desconto_percentual'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function discountApprovalStillValid(?OportunidadeProduto $editing, array $payload): bool
    {
        if (! $editing || ! $editing->desconto_aprovado_por) {
            return false;
        }

        return (int) $editing->produto_id === (int) $payload['produto_id']
            && round((float) $editing->preco_negociado, 2) === round((float) ($payload['preco_negociado'] ?? 0), 2)
            && round((float) $editing->desconto_percentual, 2) === round((float) ($payload['desconto_percentual'] ?? 0), 2);
    }

    protected function currentUserCanApproveDiscount(): bool
    {
        try {
            return (bool) Auth::user()?->hasPermissionTo(PermissoesEnum::AprovarDesconto->value);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }

    protected function fillProductPricingFromSelectedProduct(): void
    {
        $produtoId = (int) ($this->productForm['produto_id'] ?? 0);

        if (! $produtoId) {
            $this->productForm['preco_tabela'] = '';
            $this->productForm['preco_minimo'] = '';
            $this->productForm['desconto_percentual'] = 0;
            $this->productForm['preco_negociado'] = '';

            return;
        }

        $produto = Produto::query()->find($produtoId);

        if (! $produto) {
            return;
        }

        $this->productForm['preco_tabela'] = $produto->preco_tabela !== null ? number_format((float) $produto->preco_tabela, 2, '.', '') : '';
        $this->productForm['preco_minimo'] = $produto->preco_minimo !== null ? number_format((float) $produto->preco_minimo, 2, '.', '') : '';

        $this->productForm['desconto_percentual'] = 0;

        $this->refreshProductNegotiatedPrice();
    }

    protected function refreshProductNegotiatedPrice(): void
    {
        $precoTabela = (float) ($this->productForm['preco_tabela'] ?? 0);
        $desconto = min(max((float) ($this->productForm['desconto_percentual'] ?? 0), 0), 100);

        $this->productForm['desconto_percentual'] = $desconto;
        $this->productForm['preco_negociado'] = $precoTabela > 0
            ? number_format(round($precoTabela * (1 - ($desconto / 100)), 2), 2, '.', '')
            : '';
    }

    public function editProductLink(int $productLinkId): void
    {
        $registro = OportunidadeProduto::query()->findOrFail($productLinkId);

        Gate::authorize('update', $registro);

        $this->ensureBelongsToSelectedOpportunity($registro->oportunidade_id);

        $this->activeDrawerTab = 'products';
        $produto = $registro->produto;
        $precoTabela = $produto?->preco_tabela !== null ? (float) $produto->preco_tabela : 0.0;
        $precoNegociado = $registro->preco_negociado !== null ? (float) $registro->preco_negociado : $precoTabela;
        $desconto = $registro->desconto_percentual !== null
            ? (float) $registro->desconto_percentual
            : $this->calculateDiscountPercent($precoTabela, $precoNegociado);

        $this->productForm = [
            'id' => $registro->id,
            'produto_id' => $registro->produto_id,
            'quantidade' => $registro->quantidade,
            'preco_tabela' => $precoTabela > 0 ? number_format($precoTabela, 2, '.', '') : '',
            'preco_minimo' => $produto?->preco_minimo !== null ? number_format((float) $produto->preco_minimo, 2, '.', '') : '',
            'desconto_percentual' => number_format($desconto, 2, '.', ''),
            'preco_negociado' => $precoNegociado > 0 ? number_format($precoNegociado, 2, '.', '') : '',
            'observacao' => $registro->observacao ?? '',
        ];
    }

    public function deleteProductLink(int $productLinkId): void
    {
        $registro = OportunidadeProduto::query()->findOrFail($productLinkId);

        Gate::authorize('delete', $registro);

        $this->ensureBelongsToSelectedOpportunity($registro->oportunidade_id);
        $registro->delete();

        if ((int) ($this->productForm['id'] ?? 0) === $productLinkId) {
            $this->resetProductForm();
        }

        Notification::make()
            ->title('Produto removido')
            ->success()
            ->send();
    }

    public function resetProductForm(): void
    {
        $this->productForm = [
            'id' => null,
            'produto_id' => '',
            'quantidade' => 1,
            'preco_tabela' => '',
            'preco_minimo' => '',
            'desconto_percentual' => 0,
            'preco_negociado' => '',
            'observacao' => '',
        ];
    }

    protected function calculateDiscountPercent(float $precoTabela, float $precoFinal): float
    {
        if ($precoTabela <= 0 || $precoFinal <= 0) {
            return 0.0;
        }

        return round(max(0, min(100, (1 - ($precoFinal / $precoTabela)) * 100)), 2);
    }

    public function saveInteraction(): void
    {
        $oportunidade = $this->getSelectedOpportunityForEditing();

        $editing = $this->interactionForm['id'] ? OportunidadeInteracao::query()->findOrFail((int) $this->interactionForm['id']) : null;

        if ($editing) {
            Gate::authorize('update', $editing);
        } else {
            Gate::authorize('create', OportunidadeInteracao::class);
        }

        $validated = $this->validate($this->interactionRules());

        $payload = [
            'oportunidade_id' => $oportunidade->id,
            'user_id' => (int) $validated['interactionForm']['user_id'],
            'tipo' => $validated['interactionForm']['tipo'],
            'nota' => trim((string) $validated['interactionForm']['nota']),
            'ocorreu_em' => $validated['interactionForm']['ocorreu_em'],
        ];

        $registro = $editing ?? new OportunidadeInteracao;
        $registro->fill($payload);
        $registro->save();

        $this->resetInteractionForm();

        Notification::make()
            ->title($editing ? 'Interacao atualizada' : 'Interacao registrada')
            ->success()
            ->send();
    }

    public function editInteraction(int $interactionId): void
    {
        $registro = OportunidadeInteracao::query()->findOrFail($interactionId);

        Gate::authorize('update', $registro);

        $this->ensureBelongsToSelectedOpportunity($registro->oportunidade_id);

        $this->activeDrawerTab = 'interactions';
        $this->interactionForm = [
            'id' => $registro->id,
            'user_id' => $registro->user_id,
            'tipo' => $registro->tipo,
            'nota' => $registro->nota,
            'ocorreu_em' => $registro->ocorreu_em?->format('Y-m-d\TH:i'),
        ];
    }

    public function deleteInteraction(int $interactionId): void
    {
        $registro = OportunidadeInteracao::query()->findOrFail($interactionId);

        Gate::authorize('delete', $registro);

        $this->ensureBelongsToSelectedOpportunity($registro->oportunidade_id);
        $registro->delete();

        if ((int) ($this->interactionForm['id'] ?? 0) === $interactionId) {
            $this->resetInteractionForm();
        }

        Notification::make()
            ->title('Interacao removida')
            ->success()
            ->send();
    }

    public function resetInteractionForm(): void
    {
        $this->interactionForm = [
            'id' => null,
            'user_id' => Auth::id(),
            'tipo' => 'observacao',
            'nota' => '',
            'ocorreu_em' => now()->format('Y-m-d\TH:i'),
        ];
    }

    public function saveTask(): void
    {
        $oportunidade = $this->getSelectedOpportunityForEditing();

        $editing = $this->taskForm['id'] ? OportunidadeTarefa::query()->findOrFail((int) $this->taskForm['id']) : null;

        if ($editing) {
            Gate::authorize('update', $editing);
        } else {
            Gate::authorize('create', OportunidadeTarefa::class);
        }

        $validated = $this->validate($this->taskRules());

        $payload = [
            'oportunidade_id' => $oportunidade->id,
            'user_id' => (int) $validated['taskForm']['user_id'],
            'titulo' => trim((string) $validated['taskForm']['titulo']),
            'status' => $validated['taskForm']['status'],
            'data_prevista' => blank($validated['taskForm']['data_prevista']) ? null : $validated['taskForm']['data_prevista'],
        ];

        $registro = $editing ?? new OportunidadeTarefa;
        $registro->fill($payload);
        $registro->save();

        $this->resetTaskForm();

        Notification::make()
            ->title($editing ? 'Tarefa atualizada' : 'Tarefa criada')
            ->success()
            ->send();
    }

    public function editTask(int $taskId): void
    {
        $registro = OportunidadeTarefa::query()->findOrFail($taskId);

        Gate::authorize('update', $registro);

        $this->ensureBelongsToSelectedOpportunity($registro->oportunidade_id);

        $this->activeDrawerTab = 'tasks';
        $this->taskForm = [
            'id' => $registro->id,
            'user_id' => $registro->user_id,
            'titulo' => $registro->titulo,
            'status' => $registro->status,
            'data_prevista' => $registro->data_prevista?->format('Y-m-d'),
        ];
    }

    public function deleteTask(int $taskId): void
    {
        $registro = OportunidadeTarefa::query()->findOrFail($taskId);

        Gate::authorize('delete', $registro);

        $this->ensureBelongsToSelectedOpportunity($registro->oportunidade_id);
        $registro->delete();

        if ((int) ($this->taskForm['id'] ?? 0) === $taskId) {
            $this->resetTaskForm();
        }

        Notification::make()
            ->title('Tarefa removida')
            ->success()
            ->send();
    }

    public function resetTaskForm(): void
    {
        $this->taskForm = [
            'id' => null,
            'user_id' => Auth::id(),
            'titulo' => '',
            'status' => 'pendente',
            'data_prevista' => '',
        ];
    }

    protected function getBoardQuery(): Builder
    {
        $query = Oportunidade::query()
            ->with([
                'cliente.categoriaSegmento',
                'user',
                'etapa',
                'vendaOperacaoPedido',
                'oportunidadeProdutos' => fn ($builder) => $builder
                    ->with('produto')
                    ->latest('updated_at'),
            ])
            ->withMax('oportunidadeInteracoes as last_interaction_at', 'ocorreu_em')
            ->latest('updated_at');

        if (filled($search = trim($this->search))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('titulo', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function (Builder $clienteQuery) use ($search): void {
                        $clienteQuery
                            ->where('razao_social', 'like', "%{$search}%")
                            ->orWhere('nome_fantasia', 'like', "%{$search}%")
                            ->orWhere('nome_completo', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($this->ownerFilter) {
            $query->where('user_id', $this->ownerFilter);
        }

        if ($this->segmentFilter) {
            $query->whereHas('cliente', fn (Builder $builder) => $builder->where('id_categoria_segmento', $this->segmentFilter));
        }

        if ($this->temperatureFilter !== '') {
            $query->where('temperatura', $this->temperatureFilter);
        }

        if ($this->lastInteractionDays !== '') {
            $since = now()->subDays((int) $this->lastInteractionDays);

            $query->whereHas('oportunidadeInteracoes', fn (Builder $builder) => $builder->where('ocorreu_em', '>=', $since));
        }

        return $query;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function opportunityRules(): array
    {
        $rules = [
            'opportunityForm.titulo' => ['required', 'string', 'max:255'],
            'opportunityForm.client_mode' => ['required', Rule::in([
                OportunidadeClienteService::MODE_EXISTING,
                OportunidadeClienteService::MODE_NEW,
            ])],
            'opportunityForm.client_lookup' => ['nullable', 'string', 'max:255'],
            'opportunityForm.cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'opportunityForm.client_razao_social' => ['nullable', 'string', 'max:255'],
            'opportunityForm.client_nome_fantasia' => ['nullable', 'string', 'max:255'],
            'opportunityForm.client_cnpj' => ['nullable', 'string'],
            'opportunityForm.client_segmento_id' => ['nullable', 'integer', 'exists:categorias_segmentos,id'],
            'opportunityForm.client_status_id' => ['nullable', 'integer', 'exists:status_clientes,id'],
            'opportunityForm.client_nome_completo' => ['nullable', 'string', 'max:255'],
            'opportunityForm.client_cargo' => ['nullable', 'string', 'max:100'],
            'opportunityForm.client_email' => ['nullable', 'email', 'max:255'],
            'opportunityForm.client_telefone' => ['nullable', 'string', 'max:20'],
            'opportunityForm.client_cidade' => ['nullable', 'string', 'max:100'],
            'opportunityForm.client_uf' => ['nullable', Rule::in(array_keys($this->getUfOptions()))],
            'opportunityForm.client_observacao' => ['nullable', 'string', 'max:2000'],
            'opportunityForm.etapa_id' => ['required', 'integer', 'exists:etapas,id'],
            'opportunityForm.user_id' => ['required', 'integer', 'exists:users,id'],
            'opportunityForm.temperatura' => ['required', Rule::in(array_keys(Oportunidade::temperaturaOptions()))],
            'opportunityForm.valor_estimado' => ['nullable', 'numeric', 'min:0'],
            'opportunityForm.motivo_fechamento' => ['nullable', 'string'],
            'opportunityForm.notas' => ['nullable', 'string'],
        ];

        if (($this->opportunityForm['client_mode'] ?? OportunidadeClienteService::MODE_EXISTING) === OportunidadeClienteService::MODE_NEW) {
            $rules['opportunityForm.client_razao_social'][] = 'required';
            $rules['opportunityForm.client_cnpj'][] = 'required';
            $rules['opportunityForm.client_cnpj'][] = new FlexibleTaxIdentifierRule();
            $rules['opportunityForm.client_segmento_id'][] = 'required';
            $rules['opportunityForm.client_status_id'][] = 'required';
            $rules['opportunityForm.client_nome_completo'][] = 'required';
            $rules['opportunityForm.client_telefone'][] = $this->validPhoneRule();
        } else {
            $rules['opportunityForm.client_lookup'][] = 'required';
        }

        if ($this->selectedStageIsClosing()) {
            $rules['opportunityForm.motivo_fechamento'][] = 'required';
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function productRules(): array
    {
        return [
            'productForm.produto_id' => ['required', 'integer', 'exists:produtos,id'],
            'productForm.quantidade' => ['required', 'numeric', 'decimal:0,4', 'min:0.0001'],
            'productForm.preco_negociado' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'productForm.desconto_percentual' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'productForm.observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function interactionRules(): array
    {
        return [
            'interactionForm.user_id' => ['required', 'integer', 'exists:users,id'],
            'interactionForm.tipo' => ['required', Rule::in(array_keys(OportunidadeInteracao::tipoOptions()))],
            'interactionForm.nota' => ['required', 'string'],
            'interactionForm.ocorreu_em' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function taskRules(): array
    {
        return [
            'taskForm.user_id' => ['required', 'integer', 'exists:users,id'],
            'taskForm.titulo' => ['required', 'string', 'max:255'],
            'taskForm.status' => ['required', Rule::in(array_keys(OportunidadeTarefa::statusOptions()))],
            'taskForm.data_prevista' => ['nullable', 'date'],
        ];
    }

    protected function fillOpportunityForm(?Oportunidade $opportunity = null, ?int $stageId = null): void
    {
        $service = app(OportunidadeClienteService::class);

        $this->opportunityForm = [
            'titulo' => $opportunity?->titulo ?? '',
            ...($opportunity?->cliente
                ? $service->existingClientFormState($opportunity->cliente)
                : $service->blankFormState()),
            'etapa_id' => $opportunity?->etapa_id ?? $stageId ?? '',
            'user_id' => $opportunity?->user_id ?? Auth::id(),
            'temperatura' => $opportunity?->temperatura ?? 'warm',
            'valor_estimado' => $opportunity?->valor_estimado,
            'motivo_fechamento' => $opportunity?->motivo_fechamento ?? '',
            'notas' => $opportunity?->notas ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function applyClientFormState(array $state): void
    {
        $this->opportunityForm = [
            ...$this->opportunityForm,
            ...$state,
        ];
    }

    protected function validPhoneRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail): void {
            if (blank($value)) {
                return;
            }

            $digits = preg_replace('/\D/', '', (string) $value);

            if (! in_array(strlen($digits), [10, 11], true)) {
                $fail('Informe um telefone valido com DDD.');

                return;
            }

            $ddd = (int) substr($digits, 0, 2);

            if (($ddd < 11) || ($ddd > 99)) {
                $fail('DDD invalido.');

                return;
            }

            if ((strlen($digits) === 11) && ($digits[2] !== '9')) {
                $fail('Numero de celular deve comecar com 9 apos o DDD.');
            }
        };
    }

    protected function getSelectedOpportunityForEditing(): Oportunidade
    {
        $oportunidade = $this->getSelectedOpportunity();

        abort_unless($oportunidade, 404);

        Gate::authorize('update', $oportunidade);

        return $oportunidade;
    }

    protected function ensureBelongsToSelectedOpportunity(int $oportunidadeId): void
    {
        abort_unless($this->selectedOpportunityId === $oportunidadeId, 404);
    }

    protected function findOpportunityOrFail(int $opportunityId): Oportunidade
    {
        return Oportunidade::query()->findOrFail($opportunityId);
    }

    /**
     * @param  array<string, mixed>|null  $stage
     * @return array<string, mixed>
     */
    protected function serializeOpportunityCard(Oportunidade $opportunity, ?array $stage = null): array
    {
        $produtos = $opportunity->oportunidadeProdutos
            ->pluck('produto.nome')
            ->filter()
            ->take(3)
            ->values()
            ->all();

        $cliente = $opportunity->cliente;
        $responsavel = $opportunity->user;
        $ultimaInteracao = $opportunity->last_interaction_at ? now()->parse($opportunity->last_interaction_at) : null;
        $etapa = $opportunity->etapa;
        $valorEstimado = $opportunity->calcularValorEstimado();
        $convertida = (bool) ($opportunity->venda_operacao_pedido_id || $opportunity->convertida_em);

        return [
            'id' => $opportunity->id,
            'title' => $opportunity->titulo,
            'company' => $cliente?->razao_social,
            'contact' => $cliente?->nome_completo,
            'email' => $cliente?->email,
            'segment' => $cliente?->categoriaSegmento?->nome,
            'owner' => $responsavel?->name,
            'owner_initials' => $this->extractInitials($responsavel?->name),
            'temperature' => $opportunity->temperatura,
            'temperature_label' => Oportunidade::temperaturaOptions()[$opportunity->temperatura] ?? $opportunity->temperatura,
            'value' => $valorEstimado,
            'value_formatted' => $valorEstimado > 0 ? $this->formatMoney($valorEstimado) : null,
            'products' => $produtos,
            'extra_products_count' => max($opportunity->oportunidadeProdutos->count() - count($produtos), 0),
            'last_interaction_label' => $ultimaInteracao ? $this->formatRelativeDate($ultimaInteracao) : 'Sem interacao',
            'last_interaction_at' => $ultimaInteracao?->format('d/m/Y H:i'),
            'stage_id' => $stage['id'] ?? $opportunity->etapa_id,
            'stage_name' => $stage['nome'] ?? $etapa?->nome ?? 'Sem etapa',
            'stage_slug' => $stage['slug'] ?? $etapa?->slug ?? '',
            'stage_color' => $stage['cor'] ?? $etapa?->cor ?? $this->resolveStageColor(0),
            'stage_is_closing' => (bool) ($stage['fechamento'] ?? $etapa?->fechamento ?? false),
            'is_converted' => $convertida,
            'can_update' => Gate::allows('update', $opportunity),
            'can_view' => Gate::allows('view', $opportunity),
            'can_convert_to_sale' => ! $convertida
                && Gate::allows('update', $opportunity)
                && Gate::allows('create', VendaOperacao::class),
        ];
    }

    /**
     * @return array<int, array{id:int,nome:string,slug:string|null,cor:string,fechamento:bool}>
     */
    protected function getStageMetasById(): array
    {
        return Etapa::query()
            ->orderBy('ordem')
            ->orderBy('id')
            ->get()
            ->values()
            ->mapWithKeys(fn (Etapa $etapa, int $index): array => [
                $etapa->id => [
                    'id' => $etapa->id,
                    'nome' => $etapa->nome,
                    'slug' => $etapa->slug,
                    'cor' => $etapa->cor ?: $this->resolveStageColor($index),
                    'fechamento' => (bool) $etapa->fechamento,
                ],
            ])
            ->all();
    }

    protected function resolveDefaultStageId(): ?int
    {
        return Etapa::query()
            ->orderBy('fechamento')
            ->orderBy('ordem')
            ->value('id');
    }

    protected function resolveStageColor(int $index): string
    {
        $palette = [
            '#1d4ed8',
            '#0f766e',
            '#c2410c',
            '#7c3aed',
            '#059669',
            '#be123c',
        ];

        return $palette[$index % count($palette)];
    }

    protected function formatMoney(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    protected function formatRelativeDate(CarbonInterface $date): string
    {
        return $date
            ->locale('pt_BR')
            ->diffForHumans(now(), CarbonInterface::DIFF_RELATIVE_TO_NOW, false, 2);
    }

    protected function extractInitials(?string $name): string
    {
        if (blank($name)) {
            return '?';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
            ->implode('');
    }
}
