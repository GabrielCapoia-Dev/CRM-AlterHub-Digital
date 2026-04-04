<?php

namespace App\Filament\Resources\Oportunidades\Pages;

use App\Filament\Resources\Oportunidades\OportunidadeResource;
use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\OportunidadeInteracao;
use App\Models\OportunidadeProduto;
use App\Models\OportunidadeTarefa;
use App\Models\Produto;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KanbanOportunidades extends Page
{
    protected static string $resource = OportunidadeResource::class;

    protected static ?string $title = 'CRM - Kanban';

    protected string $view = 'filament.resources.oportunidades.pages.kanban-oportunidades';

    public string $search = '';

    public string $temperatureFilter = '';

    public string $lastInteractionDays = '';

    public ?int $ownerFilter = null;

    public ?int $segmentFilter = null;

    public bool $drawerOpen = false;

    public string $drawerMode = 'create';

    public string $activeDrawerTab = 'summary';

    public ?int $selectedOpportunityId = null;

    public bool $closingReasonModalOpen = false;

    public ?int $pendingMoveOpportunityId = null;

    public ?int $pendingMoveStageId = null;

    public string $pendingMoveReason = '';

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
    }

    public function render(): View
    {
        return view($this->getView(), $this->getViewData());
    }

    public function updated(string $name, mixed $value): void
    {
        if (($name === 'opportunityForm.etapa_id') && (! $this->selectedStageIsClosing())) {
            $this->opportunityForm['motivo_fechamento'] = '';
        }

        if ($name === 'segmentFilter') {
            $this->segmentFilter = blank($value) ? null : (int) $value;
        }

        if ($name === 'ownerFilter') {
            $this->ownerFilter = blank($value) ? null : (int) $value;
        }
    }

    public function getListUrl(): string
    {
        return static::getResource()::getUrl('list');
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
                'oportunidadeProdutos' => fn (Builder $query) => $query
                    ->with('produto')
                    ->latest('updated_at'),
                'oportunidadeInteracoes' => fn (Builder $query) => $query
                    ->with('user')
                    ->latest('ocorreu_em'),
                'oportunidadeTarefas' => fn (Builder $query) => $query
                    ->with('user')
                    ->orderBy('data_prevista')
                    ->latest('updated_at'),
                'oportunidadeMovimentacoes' => fn (Builder $query) => $query
                    ->with(['user', 'etapaOrigem', 'etapaDestino'])
                    ->latest('movido_em'),
            ])
            ->find($this->selectedOpportunityId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBoardColumns(): array
    {
        $etapas = Etapa::query()
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        /** @var EloquentCollection<int, Oportunidade> $oportunidades */
        $oportunidades = $this->getBoardQuery()->get();
        $oportunidadesPorEtapa = $oportunidades->groupBy('etapa_id');

        return $etapas
            ->values()
            ->map(function (Etapa $etapa, int $index) use ($oportunidadesPorEtapa): array {
                /** @var EloquentCollection<int, Oportunidade> $cards */
                $cards = $oportunidadesPorEtapa->get($etapa->id, new EloquentCollection());
                $soma = (float) $cards->sum(fn (Oportunidade $oportunidade): float => (float) ($oportunidade->valor_estimado ?? 0));

                return [
                    'id' => $etapa->id,
                    'nome' => $etapa->nome,
                    'slug' => $etapa->slug,
                    'cor' => $etapa->cor ?: $this->resolveStageColor($index),
                    'fechamento' => $etapa->fechamento,
                    'count' => $cards->count(),
                    'sum' => $soma,
                    'sum_formatted' => $this->formatMoney($soma),
                    'cards' => $cards
                        ->map(fn (Oportunidade $oportunidade): array => $this->serializeOpportunityCard($oportunidade))
                        ->all(),
                ];
            })
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
     * @return array<int, array{id:int,nome:string,segmento:?string}>
     */
    public function getClientOptions(): array
    {
        return Cliente::query()
            ->with('categoriaSegmento')
            ->orderBy('razao_social')
            ->get(['id', 'razao_social', 'id_categoria_segmento'])
            ->map(fn (Cliente $cliente): array => [
                'id' => $cliente->id,
                'nome' => $cliente->razao_social,
                'segmento' => $cliente->categoriaSegmento?->nome,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int,nome:string}>
     */
    public function getProductOptions(): array
    {
        return Produto::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (Produto $produto): array => [
                'id' => $produto->id,
                'nome' => $produto->nome,
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

    public function getSelectedClientSegmentName(): ?string
    {
        $clienteId = (int) ($this->opportunityForm['cliente_id'] ?? 0);

        if (! $clienteId) {
            return null;
        }

        return Cliente::query()
            ->with('categoriaSegmento')
            ->find($clienteId)
            ?->categoriaSegmento
            ?->nome;
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
            'opportunityForm.cliente_id' => 'cliente',
            'opportunityForm.etapa_id' => 'etapa',
            'opportunityForm.user_id' => 'responsavel',
            'opportunityForm.temperatura' => 'temperatura',
            'opportunityForm.valor_estimado' => 'valor estimado',
            'opportunityForm.motivo_fechamento' => 'motivo de fechamento',
            'opportunityForm.notas' => 'notas',
        ]);

        $payload = [
            'titulo' => trim((string) $validated['opportunityForm']['titulo']),
            'cliente_id' => (int) $validated['opportunityForm']['cliente_id'],
            'etapa_id' => (int) $validated['opportunityForm']['etapa_id'],
            'user_id' => (int) $validated['opportunityForm']['user_id'],
            'temperatura' => $validated['opportunityForm']['temperatura'],
            'valor_estimado' => blank($validated['opportunityForm']['valor_estimado']) ? null : (float) $validated['opportunityForm']['valor_estimado'],
            'motivo_fechamento' => blank($validated['opportunityForm']['motivo_fechamento']) ? null : trim((string) $validated['opportunityForm']['motivo_fechamento']),
            'notas' => blank($validated['opportunityForm']['notas']) ? null : trim((string) $validated['opportunityForm']['notas']),
        ];

        $oportunidade = $selected ?? new Oportunidade();
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

        $payload = [
            'oportunidade_id' => $oportunidade->id,
            'produto_id' => (int) $validated['productForm']['produto_id'],
            'preco_negociado' => blank($validated['productForm']['preco_negociado']) ? null : (float) $validated['productForm']['preco_negociado'],
            'observacao' => blank($validated['productForm']['observacao']) ? null : trim((string) $validated['productForm']['observacao']),
        ];

        $registro = $editing ?? new OportunidadeProduto();
        $registro->fill($payload);
        $registro->save();

        $this->resetProductForm();

        Notification::make()
            ->title($editing ? 'Produto atualizado' : 'Produto vinculado')
            ->success()
            ->send();
    }

    public function editProductLink(int $productLinkId): void
    {
        $registro = OportunidadeProduto::query()->findOrFail($productLinkId);

        Gate::authorize('update', $registro);

        $this->ensureBelongsToSelectedOpportunity($registro->oportunidade_id);

        $this->activeDrawerTab = 'products';
        $this->productForm = [
            'id' => $registro->id,
            'produto_id' => $registro->produto_id,
            'preco_negociado' => $registro->preco_negociado,
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
            'preco_negociado' => '',
            'observacao' => '',
        ];
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

        $registro = $editing ?? new OportunidadeInteracao();
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

        $registro = $editing ?? new OportunidadeTarefa();
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
                'oportunidadeProdutos' => fn (Builder $builder) => $builder
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
            'opportunityForm.cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'opportunityForm.etapa_id' => ['required', 'integer', 'exists:etapas,id'],
            'opportunityForm.user_id' => ['required', 'integer', 'exists:users,id'],
            'opportunityForm.temperatura' => ['required', Rule::in(array_keys(Oportunidade::temperaturaOptions()))],
            'opportunityForm.valor_estimado' => ['nullable', 'numeric', 'min:0'],
            'opportunityForm.motivo_fechamento' => ['nullable', 'string'],
            'opportunityForm.notas' => ['nullable', 'string'],
        ];

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
            'productForm.preco_negociado' => ['nullable', 'numeric', 'min:0'],
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
        $this->opportunityForm = [
            'titulo' => $opportunity?->titulo ?? '',
            'cliente_id' => $opportunity?->cliente_id ?? '',
            'etapa_id' => $opportunity?->etapa_id ?? $stageId ?? '',
            'user_id' => $opportunity?->user_id ?? Auth::id(),
            'temperatura' => $opportunity?->temperatura ?? 'warm',
            'valor_estimado' => $opportunity?->valor_estimado,
            'motivo_fechamento' => $opportunity?->motivo_fechamento ?? '',
            'notas' => $opportunity?->notas ?? '',
        ];
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
     * @return array<string, mixed>
     */
    protected function serializeOpportunityCard(Oportunidade $opportunity): array
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
            'value' => $opportunity->valor_estimado,
            'value_formatted' => blank($opportunity->valor_estimado) ? null : $this->formatMoney((float) $opportunity->valor_estimado),
            'products' => $produtos,
            'extra_products_count' => max($opportunity->oportunidadeProdutos->count() - count($produtos), 0),
            'last_interaction_label' => $ultimaInteracao ? $this->formatRelativeDate($ultimaInteracao) : 'Sem interacao',
            'last_interaction_at' => $ultimaInteracao?->format('d/m/Y H:i'),
        ];
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
        return 'R$ ' . number_format($value, 2, ',', '.');
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
