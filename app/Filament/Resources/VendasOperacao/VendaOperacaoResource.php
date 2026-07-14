<?php

namespace App\Filament\Resources\VendasOperacao;

use App\Enum\RolesEnum;
use App\Filament\Resources\VendasOperacao\Pages\ManageVendasOperacao;
use App\Filament\Support\Fields\TaxIdentifierField;
use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\Status\StatusCliente;
use App\Models\VendaOperacaoPedido;
use App\Rules\UniqueNormalizedTaxIdentifierRule;
use App\Services\Acesso\RoleService;
use App\Services\CRM\OportunidadeClienteService;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Operacao\VendaWorkflowService;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class VendaOperacaoResource extends Resource
{
    protected static ?string $model = VendaOperacaoPedido::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Vendas';

    protected static ?string $modelLabel = 'Venda';

    protected static ?string $pluralModelLabel = 'Vendas';

    public static ?string $slug = 'operacao/vendas';

    protected static string|UnitEnum|null $navigationGroup = 'Operacao';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return app(VendaOperacaoService::class)
            ->queryPorPerfil(auth()->user())
            ->with(['cliente', 'oportunidade', 'vendasOperacao.produto', 'vendasOperacao.produtoMovimentacao', 'aprovadoPor']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::getSaleFormComponents());
    }

    /**
     * @return array<int, Component|\Filament\Forms\Components\Component>
     */
    public static function getSaleFormComponents(): array
    {
        $canPickVendedor = app(RoleService::class)->podeEscolherVendedor(auth()->user());

        return [
            Section::make('Dados da venda')
                ->description('Cliente, data e responsavel. Use + no cliente para cadastrar na hora.')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->columns(12)
                ->columnSpanFull()
                ->schema([
                    Select::make('cliente_id')
                        ->label('Cliente')
                        ->options(fn (): array => static::clienteOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->createOptionForm(static::clienteCreateOptionForm())
                        ->createOptionUsing(fn (array $data): int => static::createClienteRapido($data))
                        ->createOptionModalHeading('Novo cliente')
                        ->createOptionAction(fn (Action $action): Action => $action
                            ->modalWidth('3xl')
                            ->modalSubmitActionLabel('Salvar cliente')
                            ->extraModalWindowAttributes(['class' => 'oa-record-modal oa-sales-modal']))
                        ->helperText('Se o cliente nao existir, clique no + para cadastrar sem sair da venda.')
                        ->columnSpan(6),

                    DatePicker::make('data_venda')
                        ->label('Data')
                        ->default(now())
                        ->required()
                        ->columnSpan(3),

                    Select::make('vendedor_user_id')
                        ->label('Vendedor')
                        ->options(fn (): array => static::vendedorOptions())
                        ->default(fn (): ?int => auth()->id())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->disabled(! $canPickVendedor)
                        ->dehydrated()
                        ->helperText($canPickVendedor
                            ? 'Selecione o vendedor responsavel pela venda.'
                            : 'Vendedor fixo no seu usuario.')
                        ->columnSpan(3),

                    Textarea::make('observacao')
                        ->label('Observacao')
                        ->rows(2)
                        ->placeholder('Opcional')
                        ->columnSpanFull(),

                    Hidden::make('origem_pedido_id'),
                ]),

            Section::make('Itens')
                ->description('Produto, quantidade, desconto e preco. Abaixo do minimo a venda vai para aprovacao.')
                ->icon(Heroicon::OutlinedCube)
                ->columnSpanFull()
                ->schema([
                    Repeater::make('itens')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Adicionar produto')
                        ->reorderable(false)
                        ->columns(12)
                        ->columnSpanFull()
                        ->itemLabel(function (array $state): ?string {
                            if (! filled($state['produto_id'] ?? null)) {
                                return 'Novo item';
                            }

                            $produto = Produto::query()->find($state['produto_id']);

                            return $produto?->nome ?? 'Item';
                        })
                        ->collapsible()
                        ->schema([
                            Select::make('produto_id')
                                ->label('Produto')
                                ->options(fn (): array => Produto::query()
                                    ->where('status', 'ativo')
                                    ->where('ativo', true)
                                    ->orderBy('nome')
                                    ->get()
                                    ->mapWithKeys(fn (Produto $produto): array => [
                                        $produto->id => $produto->codigo_interno
                                            ? "{$produto->codigo_interno} - {$produto->nome}"
                                            : $produto->nome,
                                    ])
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    if (! $state) {
                                        $set('preco_unitario', null);
                                        $set('desconto_percentual', 0);

                                        return;
                                    }

                                    $produto = Produto::query()->find($state);

                                    if (! $produto) {
                                        return;
                                    }

                                    $tabela = $produto->preco_tabela !== null
                                        ? round((float) $produto->preco_tabela, 2)
                                        : null;

                                    $set('preco_unitario', $tabela !== null
                                        ? number_format($tabela, 2, '.', '')
                                        : null);
                                    $set('desconto_percentual', 0);
                                })
                                ->columnSpan(12),

                            Placeholder::make('produto_contexto')
                                ->hiddenLabel()
                                ->content(function (Get $get): string {
                                    $produtoId = $get('produto_id');

                                    if (! $produtoId) {
                                        return 'Selecione um produto para ver estoque e precos.';
                                    }

                                    $produto = Produto::query()
                                        ->with('produtoMovimentacoes')
                                        ->find($produtoId);

                                    if (! $produto) {
                                        return 'Produto nao encontrado.';
                                    }

                                    $estoque = (float) ($produto->estoqueAtual() ?? 0);
                                    $tabela = $produto->preco_tabela !== null
                                        ? NumericFormat::money((float) $produto->preco_tabela)
                                        : '-';
                                    $minimo = $produto->preco_minimo !== null
                                        ? NumericFormat::money((float) $produto->preco_minimo)
                                        : '-';

                                    return sprintf(
                                        'Estoque: %s %s · Tabela: %s · Minimo: %s',
                                        NumericFormat::decimal($estoque),
                                        $produto->unidade_medida ?: 'un',
                                        $tabela,
                                        $minimo,
                                    );
                                })
                                ->columnSpan(12),

                            TextInput::make('quantidade')
                                ->label('Quantidade')
                                ->numeric()
                                ->rule('decimal:0,4')
                                ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                ->minValue(0.0001)
                                ->placeholder('1')
                                ->required()
                                ->live(debounce: 300)
                                ->columnSpan(4),

                            TextInput::make('desconto_percentual')
                                ->label('Desconto')
                                ->numeric()
                                ->rule('decimal:0,2')
                                ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state ?? 0))
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->live(debounce: 300)
                                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                    static::syncPrecoFromDesconto($get, $set, $state);
                                })
                                ->columnSpan(4),

                            TextInput::make('preco_unitario')
                                ->label('Preco unitario')
                                ->numeric()
                                ->rule('decimal:0,2')
                                ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                ->prefix('R$')
                                ->minValue(0.01)
                                ->placeholder('0,00')
                                ->required()
                                ->live(debounce: 300)
                                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                    static::syncDescontoFromPreco($get, $set, $state);
                                })
                                ->helperText(function (Get $get): ?string {
                                    $produtoId = $get('produto_id');
                                    $preco = (float) ($get('preco_unitario') ?? 0);

                                    if (! $produtoId || $preco <= 0) {
                                        return null;
                                    }

                                    $produto = Produto::query()->find($produtoId);
                                    $minimo = $produto?->preco_minimo !== null ? (float) $produto->preco_minimo : 0.0;

                                    if ($minimo > 0 && $preco < $minimo) {
                                        return 'Abaixo do minimo — esta venda ira para aprovacao.';
                                    }

                                    return null;
                                })
                                ->columnSpan(4),

                            Placeholder::make('resumo_item')
                                ->label('Valores do item')
                                ->content(fn (Get $get): HtmlString => static::renderItemTotals($get))
                                ->columnSpanFull(),
                        ]),

                    Placeholder::make('resumo_venda')
                        ->label('Totais da venda')
                        ->content(fn (Get $get): HtmlString => static::renderSaleTotals($get))
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array{
     *     quantidade: float,
     *     preco_tabela: float,
     *     preco_final: float,
     *     desconto_percentual: float,
     *     total_sem_desconto: float,
     *     total_com_desconto: float,
     *     economia: float
     * }
     */
    protected static function calcularLinhaItem(Get $get): array
    {
        $quantidade = max(0, (float) ($get('quantidade') ?? 0));
        $precoFinal = max(0, (float) ($get('preco_unitario') ?? 0));
        $desconto = min(max((float) ($get('desconto_percentual') ?? 0), 0), 100);

        $produto = filled($get('produto_id'))
            ? Produto::query()->find($get('produto_id'))
            : null;

        $precoTabela = $produto?->preco_tabela !== null
            ? (float) $produto->preco_tabela
            : 0.0;

        if ($precoTabela <= 0 && $precoFinal > 0 && $desconto < 100) {
            $precoTabela = $desconto > 0
                ? round($precoFinal / (1 - ($desconto / 100)), 2)
                : $precoFinal;
        }

        $baseTabela = $precoTabela > 0 ? $precoTabela : $precoFinal;
        $totalSemDesconto = round($quantidade * $baseTabela, 2);
        $totalComDesconto = round($quantidade * $precoFinal, 2);
        $economia = round(max(0, $totalSemDesconto - $totalComDesconto), 2);

        return [
            'quantidade' => $quantidade,
            'preco_tabela' => round($baseTabela, 2),
            'preco_final' => round($precoFinal, 2),
            'desconto_percentual' => round($desconto, 2),
            'total_sem_desconto' => $totalSemDesconto,
            'total_com_desconto' => $totalComDesconto,
            'economia' => $economia,
        ];
    }

    protected static function renderItemTotals(Get $get): HtmlString
    {
        $calc = static::calcularLinhaItem($get);

        if ($calc['quantidade'] <= 0 && $calc['preco_final'] <= 0) {
            return new HtmlString(
                '<div class="oa-sale-calc oa-sale-calc--empty">Informe quantidade e desconto/preco para ver os totais do item.</div>'
            );
        }

        return new HtmlString(
            '<div class="oa-sale-calc">'
            .'<div class="oa-sale-calc__grid">'
            .static::calcCard('Preco final unit.', NumericFormat::money($calc['preco_final']), 'Com desconto aplicado')
            .static::calcCard('Total sem desconto', NumericFormat::money($calc['total_sem_desconto']), 'Qtd. x preco de tabela')
            .static::calcCard('Total com desconto', NumericFormat::money($calc['total_com_desconto']), 'Qtd. x preco final', highlight: true)
            .static::calcCard('Economia', NumericFormat::money($calc['economia']), $calc['desconto_percentual'].'% de desconto')
            .'</div>'
            .'</div>'
        );
    }

    protected static function renderSaleTotals(Get $get): HtmlString
    {
        $itens = $get('itens');

        if (! is_array($itens) || $itens === []) {
            return new HtmlString(
                '<div class="oa-sale-calc oa-sale-calc--empty">Adicione itens para ver o total da venda.</div>'
            );
        }

        $totalSemDesconto = 0.0;
        $totalComDesconto = 0.0;
        $itensValidos = 0;

        foreach ($itens as $item) {
            if (! is_array($item) || blank($item['produto_id'] ?? null)) {
                continue;
            }

            $quantidade = max(0, (float) ($item['quantidade'] ?? 0));
            $precoFinal = max(0, (float) ($item['preco_unitario'] ?? 0));
            $desconto = min(max((float) ($item['desconto_percentual'] ?? 0), 0), 100);

            if ($quantidade <= 0 || $precoFinal <= 0) {
                continue;
            }

            $produto = Produto::query()->find($item['produto_id']);
            $precoTabela = $produto?->preco_tabela !== null ? (float) $produto->preco_tabela : 0.0;

            if ($precoTabela <= 0) {
                $precoTabela = $desconto > 0 && $desconto < 100
                    ? round($precoFinal / (1 - ($desconto / 100)), 2)
                    : $precoFinal;
            }

            $baseTabela = $precoTabela > 0 ? $precoTabela : $precoFinal;
            $totalSemDesconto += round($quantidade * $baseTabela, 2);
            $totalComDesconto += round($quantidade * $precoFinal, 2);
            $itensValidos++;
        }

        if ($itensValidos === 0) {
            return new HtmlString(
                '<div class="oa-sale-calc oa-sale-calc--empty">Preencha quantidade e preco dos itens para calcular o total.</div>'
            );
        }

        $economia = round(max(0, $totalSemDesconto - $totalComDesconto), 2);

        return new HtmlString(
            '<div class="oa-sale-calc oa-sale-calc--sale">'
            .'<div class="oa-sale-calc__grid">'
            .static::calcCard('Itens', (string) $itensValidos, 'Com valores informados')
            .static::calcCard('Total sem desconto', NumericFormat::money($totalSemDesconto), 'Soma bruta da venda')
            .static::calcCard('Total final', NumericFormat::money($totalComDesconto), 'Valor total descontado', highlight: true)
            .static::calcCard('Desconto total', NumericFormat::money($economia), 'Economia na venda')
            .'</div>'
            .'</div>'
        );
    }

    /**
     * @return list<string>
     */
    protected static function approvalReasonLines(VendaOperacaoPedido $pedido): array
    {
        $motivos = is_array($pedido->motivos_aprovacao)
            ? $pedido->motivos_aprovacao
            : [];
        $linhas = [];

        if (isset($motivos['desconto'])) {
            $linhas[] = (string) data_get(
                $motivos,
                'desconto.mensagem',
                'Há item vendido abaixo do preço mínimo.',
            );
        }

        foreach ((array) ($motivos['estoque'] ?? []) as $falta) {
            if (! is_array($falta)) {
                continue;
            }

            $produto = trim((string) ($falta['produto'] ?? ''));

            if ($produto === '') {
                $produto = 'Produto #'.($falta['produto_id'] ?? 'não informado');
            }

            $linhas[] = sprintf(
                '%s — solicitado: %s; disponível: %s; déficit: %s.',
                $produto,
                NumericFormat::decimal($falta['solicitado'] ?? 0, 4),
                NumericFormat::decimal($falta['disponivel'] ?? 0, 4),
                NumericFormat::decimal($falta['deficit'] ?? 0, 4),
            );
        }

        return $linhas;
    }

    protected static function renderApprovalReasons(VendaOperacaoPedido $pedido): HtmlString
    {
        $linhas = static::approvalReasonLines($pedido);
        $lista = collect($linhas)
            ->map(fn (string $linha): string => '<li>'.e($linha).'</li>')
            ->implode('');

        return new HtmlString(
            '<div class="space-y-2">'
            .'<p>Confira os motivos antes de decidir sobre esta venda.</p>'
            .($lista !== '' ? '<ul class="list-disc space-y-1 ps-5">'.$lista.'</ul>' : '<p>Nenhum motivo detalhado foi registrado.</p>')
            .'<p class="text-sm text-gray-500">A venda só será confirmada quando houver estoque suficiente.</p>'
            .'</div>'
        );
    }

    protected static function calcCard(string $label, string $value, string $hint, bool $highlight = false): string
    {
        $class = $highlight ? 'oa-sale-calc__card oa-sale-calc__card--highlight' : 'oa-sale-calc__card';

        return '<article class="'.$class.'">'
            .'<span class="oa-sale-calc__label">'.e($label).'</span>'
            .'<strong class="oa-sale-calc__value">'.e($value).'</strong>'
            .'<small class="oa-sale-calc__hint">'.e($hint).'</small>'
            .'</article>';
    }

    /**
     * @return array<int|string, string>
     */
    protected static function clienteOptions(): array
    {
        return Cliente::query()
            ->orderBy('razao_social')
            ->get()
            ->mapWithKeys(fn (Cliente $cliente): array => [
                $cliente->id => $cliente->codigo_interno
                    ? "{$cliente->codigo_interno} - {$cliente->razao_social}"
                    : $cliente->razao_social,
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected static function vendedorOptions(): array
    {
        $query = User::query()
            ->role(RolesEnum::Vendedor->value)
            ->where('email_approved', true)
            ->orderBy('name');

        $options = $query
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();

        $auth = auth()->user();

        if ($auth && ! array_key_exists($auth->id, $options)) {
            $options = [$auth->id => $auth->name] + $options;
        }

        return $options;
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function clienteCreateOptionForm(): array
    {
        $defaultStatusId = app(OportunidadeClienteService::class)->defaultStatusId();

        return [
            TextInput::make('razao_social')
                ->label('Razao social')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('nome_fantasia')
                ->label('Nome fantasia')
                ->maxLength(255),

            TaxIdentifierField::make('cnpj')
                ->required()
                ->rule(new UniqueNormalizedTaxIdentifierRule(
                    table: 'clientes',
                    column: 'cnpj',
                )),

            Select::make('id_categoria_segmento')
                ->label('Segmento')
                ->options(fn (): array => CategoriaSegmento::query()->orderBy('nome')->pluck('nome', 'id')->all())
                ->searchable()
                ->preload()
                ->required(),

            Select::make('id_status_cliente')
                ->label('Status')
                ->options(fn (): array => StatusCliente::query()->orderBy('nome')->pluck('nome', 'id')->all())
                ->searchable()
                ->preload()
                ->required()
                ->default($defaultStatusId),

            TextInput::make('nome_completo')
                ->label('Contato')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->maxLength(255),

            TextInput::make('telefone')
                ->label('Telefone')
                ->mask('(99) 99999-9999')
                ->placeholder('(00) 00000-0000'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function createClienteRapido(array $data): int
    {
        $cliente = Cliente::query()->create([
            'razao_social' => trim((string) ($data['razao_social'] ?? '')),
            'nome_fantasia' => filled($data['nome_fantasia'] ?? null) ? trim((string) $data['nome_fantasia']) : null,
            'cnpj' => $data['cnpj'] ?? null,
            'id_categoria_segmento' => $data['id_categoria_segmento'] ?? null,
            'id_status_cliente' => $data['id_status_cliente']
                ?? app(OportunidadeClienteService::class)->defaultStatusId(),
            'nome_completo' => trim((string) ($data['nome_completo'] ?? '')),
            'email' => filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
            'telefone' => filled($data['telefone'] ?? null) ? trim((string) $data['telefone']) : null,
        ]);

        Notification::make()
            ->title('Cliente cadastrado')
            ->body("{$cliente->razao_social} ja pode ser usado nesta venda.")
            ->success()
            ->send();

        return (int) $cliente->id;
    }

    protected static function syncPrecoFromDesconto(Get $get, Set $set, mixed $descontoState): void
    {
        $produtoId = $get('produto_id');

        if (! $produtoId) {
            return;
        }

        $produto = Produto::query()->find($produtoId);
        $tabela = $produto?->preco_tabela !== null ? (float) $produto->preco_tabela : 0.0;

        if ($tabela <= 0) {
            return;
        }

        $desconto = min(max((float) ($descontoState ?? 0), 0), 100);
        $preco = round($tabela * (1 - ($desconto / 100)), 2);

        $set('desconto_percentual', number_format($desconto, 2, '.', ''));
        $set('preco_unitario', number_format(max($preco, 0.01), 2, '.', ''));
    }

    protected static function syncDescontoFromPreco(Get $get, Set $set, mixed $precoState): void
    {
        $produtoId = $get('produto_id');

        if (! $produtoId) {
            return;
        }

        $produto = Produto::query()->find($produtoId);
        $tabela = $produto?->preco_tabela !== null ? (float) $produto->preco_tabela : 0.0;
        $preco = (float) ($precoState ?? 0);

        if ($tabela <= 0 || $preco <= 0) {
            $set('desconto_percentual', '0.00');

            return;
        }

        $desconto = round(max(0, min(100, (1 - ($preco / $tabela)) * 100)), 2);
        $set('desconto_percentual', number_format($desconto, 2, '.', ''));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'cliente',
                'oportunidade',
                'vendasOperacao.produto',
                'vendasOperacao.produtoMovimentacao',
                'aprovadoPor',
            ]))
            ->columns([
                Split::make([
                    TextColumn::make('data_venda')
                        ->label('Data')
                        ->description('Data', position: 'above')
                        ->date('d/m/Y')
                        ->sortable()
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                    Stack::make([
                        TextColumn::make('codigo')
                            ->label('Venda')
                            ->searchable()
                            ->weight('semibold')
                            ->description(fn (VendaOperacaoPedido $record): ?string => $record->oportunidade?->titulo)
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                        TextColumn::make('cliente_nome_snapshot')
                            ->label('Cliente')
                            ->searchable()
                            ->placeholder('-')
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ]),

                    TextColumn::make('lucro_apos_impostos_total')
                        ->label('Lucro')
                        ->description('Lucro', position: 'above')
                        ->money('BRL')
                        ->sortable()
                        ->alignEnd()
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),
                ])
                    ->from('md')
                    ->extraAttributes(['class' => 'crm-list-top']),

                Grid::make([
                    'default' => 1,
                    'sm' => 2,
                    'xl' => 4,
                ])
                    ->schema([
                        TextColumn::make('status')
                            ->label('Status')
                            ->description('Status', position: 'above')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => VendaOperacaoPedido::statusOptions()[$state] ?? (string) $state)
                            ->color(fn (?string $state): string => match ($state) {
                                VendaOperacaoPedido::STATUS_RASCUNHO => 'gray',
                                VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO => 'warning',
                                VendaOperacaoPedido::STATUS_RECUSADA => 'danger',
                                VendaOperacaoPedido::STATUS_CANCELADA => 'gray',
                                VendaOperacaoPedido::STATUS_PARCIALMENTE_DESPACHADA => 'info',
                                VendaOperacaoPedido::STATUS_DEVOLVIDA_PARCIAL => 'warning',
                                VendaOperacaoPedido::STATUS_DEVOLVIDA => 'danger',
                                default => 'success',
                            })
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('itens_count')
                            ->label('Itens')
                            ->description('Itens', position: 'above')
                            ->alignEnd()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('quantidade_total')
                            ->label('Qtd. total')
                            ->description('Qtd. total', position: 'above')
                            ->alignEnd()
                            ->formatStateUsing(fn ($state): string => NumericFormat::decimal($state))
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('receita_bruta_total')
                            ->label('Receita')
                            ->description('Receita', position: 'above')
                            ->money('BRL')
                            ->sortable()
                            ->alignEnd()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),

                        TextColumn::make('custo_total_snapshot')
                            ->label('Custo')
                            ->description('Custo', position: 'above')
                            ->money('BRL')
                            ->alignEnd()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-finance']),

                TextColumn::make('motivos_aprovacao')
                    ->label('Motivos da aprovação')
                    ->description('Motivos da aprovação', position: 'above')
                    ->state(fn (VendaOperacaoPedido $record): string => implode(' ', static::approvalReasonLines($record)))
                    ->visible(fn (VendaOperacaoPedido $record): bool => static::approvalReasonLines($record) !== [])
                    ->wrap()
                    ->extraAttributes(['class' => 'crm-list-field crm-list-footer'], merge: true),

                TextColumn::make('vendedor_nome_snapshot')
                    ->label('Vendedor')
                    ->description('Vendedor', position: 'above')
                    ->searchable()
                    ->placeholder('-')
                    ->extraAttributes(['class' => 'crm-list-field crm-list-footer'], merge: true),
            ])
            ->defaultSort('data_venda', 'desc')
            ->searchPlaceholder('Buscar por venda, cliente, vendedor ou oportunidade...')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(VendaOperacaoPedido::statusOptions()),

                SelectFilter::make('ano_referencia')
                    ->label('Ano')
                    ->options(collect(range(now()->year, now()->year - 5))->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])->all()),

                SelectFilter::make('mes_referencia')
                    ->label('Mes')
                    ->options([
                        '1' => '01',
                        '2' => '02',
                        '3' => '03',
                        '4' => '04',
                        '5' => '05',
                        '6' => '06',
                        '7' => '07',
                        '8' => '08',
                        '9' => '09',
                        '10' => '10',
                        '11' => '11',
                        '12' => '12',
                    ]),

                SelectFilter::make('produto_id')
                    ->label('Produto')
                    ->options(fn (): array => Produto::query()
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $produtoId = $data['value'] ?? null;

                        if (! $produtoId) {
                            return $query;
                        }

                        return $query->whereHas('vendasOperacao', fn (Builder $builder) => $builder->where('produto_id', $produtoId));
                    }),
            ])
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--operation')
            ->recordActions([
                ActionGroup::make([
                    Action::make('editDraft')
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square')
                        ->modalHeading(fn (VendaOperacaoPedido $record): string => 'Editar '.$record->codigo)
                        ->modalWidth('5xl')
                        ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->schema(static::getSaleFormComponents())
                        ->fillForm(fn (VendaOperacaoPedido $record): array => app(VendaOperacaoService::class)->buildEditPayload($record))
                        ->action(function (VendaOperacaoPedido $record, array $data): void {
                            app(VendaOperacaoService::class)->updatePedido($record, $data, auth()->user());
                            Notification::make()->title('Venda atualizada')->success()->send();
                        }),

                    Action::make('products')
                        ->label('Visualizar produtos')
                        ->icon('heroicon-o-list-bullet')
                        ->modalHeading(fn (VendaOperacaoPedido $record): string => 'Produtos da venda '.$record->codigo)
                        ->modalWidth('5xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar')
                        ->extraModalWindowAttributes([
                            'class' => 'oa-record-modal oa-sale-products-modal',
                        ])
                        ->modalContent(fn (VendaOperacaoPedido $record): View => view('filament.resources.vendas-operacao.modals.products', [
                            'pedido' => $record->load(['vendasOperacao.produto', 'vendasOperacao.produtoMovimentacao', 'aprovadoPor']),
                        ])),

                    Action::make('copy')
                        ->label('Copiar venda')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->modalWidth('5xl')
                        ->slideOver(false)
                        ->modalHeading(fn (VendaOperacaoPedido $record): string => 'Copiar venda '.$record->codigo)
                        ->modalDescription('Cria um novo rascunho com cliente, produtos, quantidades e valores atuais.')
                        ->modalSubmitActionLabel('Registrar venda copiada')
                        ->extraModalWindowAttributes([
                            'class' => 'oa-record-modal oa-sales-modal oa-sales-modal--copy',
                        ])
                        ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('copy', $record) ?? false)
                        ->schema(static::getSaleFormComponents())
                        ->fillForm(fn (VendaOperacaoPedido $record): array => app(VendaOperacaoService::class)->buildCopyPayload($record))
                        ->action(function (array $data): void {
                            try {
                                $pedido = app(VendaOperacaoService::class)->createPedido($data, auth()->user());
                                static::notifySaleCreated($pedido);
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('Nao foi possivel copiar a venda')
                                    ->body(collect($exception->errors())->flatten()->implode(' '))
                                    ->danger()
                                    ->send();

                                throw $exception;
                            }
                        }),

                    Action::make('confirm')
                        ->label('Confirmar venda')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar venda e baixar estoque')
                        ->modalDescription('A confirmação registra a saída de estoque imediatamente. Se não houver saldo, a venda ficará pendente de aprovação.')
                        ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('confirm', $record) ?? false)
                        ->action(function (VendaOperacaoPedido $record): void {
                            $pedido = app(VendaWorkflowService::class)->confirmar(
                                $record,
                                auth()->user(),
                                "confirmar:venda:{$record->id}:v{$record->versao}",
                            );

                            if ($pedido->isPendenteAprovacao()) {
                                Notification::make()
                                    ->title('Venda pendente de aprovação')
                                    ->body('O estoque disponível é insuficiente. Reponha o saldo antes da aprovação.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Venda confirmada')
                                ->body('A saída de estoque foi registrada no razão.')
                                ->success()
                                ->send();
                        }),

                    Action::make('cancel')
                        ->label('Cancelar venda')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('cancel', $record) ?? false)
                        ->schema([
                            Textarea::make('justificativa')->label('Justificativa')->required()->minLength(10),
                        ])
                        ->requiresConfirmation()
                        ->action(function (VendaOperacaoPedido $record, array $data): void {
                            app(VendaWorkflowService::class)->cancelar($record, auth()->user(), $data['justificativa']);
                            Notification::make()->title('Venda cancelada e estoque compensado')->success()->send();
                        }),

                    Action::make('reopen')
                        ->label('Reabrir venda')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('reopen', $record) ?? false)
                        ->schema([
                            Textarea::make('justificativa')->label('Justificativa')->required()->minLength(10),
                        ])
                        ->requiresConfirmation()
                        ->action(function (VendaOperacaoPedido $record, array $data): void {
                            app(VendaWorkflowService::class)->reabrir($record, auth()->user(), $data['justificativa']);
                            Notification::make()->title('Venda reaberta como rascunho')->success()->send();
                        }),

                    Action::make('approveDiscount')
                        ->label('Aprovar venda')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprovar venda pendente')
                        ->modalDescription(fn (VendaOperacaoPedido $record): HtmlString => static::renderApprovalReasons($record))
                        ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('approveDiscount', $record) ?? false)
                        ->action(function (VendaOperacaoPedido $record): void {
                            try {
                                app(VendaOperacaoService::class)->approve($record, auth()->user());

                                Notification::make()
                                    ->title('Venda aprovada')
                                    ->body('A venda foi confirmada e a saída de estoque foi registrada.')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('Nao foi possivel aprovar')
                                    ->body(collect($exception->errors())->flatten()->implode(' '))
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('rejectDiscount')
                        ->label('Recusar venda')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('rejectDiscount', $record) ?? false)
                        ->form([
                            Textarea::make('motivo_recusa')
                                ->label('Motivo da recusa')
                                ->rows(3)
                                ->required()
                                ->maxLength(1000),
                        ])
                        ->action(function (VendaOperacaoPedido $record, array $data): void {
                            try {
                                app(VendaOperacaoService::class)->reject(
                                    $record,
                                    auth()->user(),
                                    $data['motivo_recusa'] ?? null,
                                );

                                Notification::make()
                                    ->title('Venda recusada')
                                    ->body('A venda foi marcada como recusada. Nenhum estoque foi movimentado.')
                                    ->warning()
                                    ->send();
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('Nao foi possivel recusar')
                                    ->body(collect($exception->errors())->flatten()->implode(' '))
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Ações')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ], position: RecordActionsPosition::AfterContent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVendasOperacao::route('/'),
        ];
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return $action
            ->label('Nova venda')
            ->modalWidth('5xl')
            ->slideOver(false)
            ->createAnother(false)
            ->modalHeading('Nova venda')
            ->modalDescription('Informe cliente, produtos e quantidades. Preço abaixo do mínimo ou estoque insuficiente aguarda aprovação.')
            ->modalSubmitActionLabel('Registrar venda')
            ->extraModalWindowAttributes([
                'class' => 'oa-record-modal oa-sales-modal oa-sales-modal--create',
            ])
            ->fillForm(fn (): array => [
                'data_venda' => now()->toDateString(),
                'vendedor_user_id' => auth()->id(),
                'itens' => [
                    [
                        'quantidade' => null,
                        'desconto_percentual' => 0,
                        'preco_unitario' => null,
                    ],
                ],
            ])
            ->using(function (array $data): VendaOperacaoPedido {
                $pedido = app(VendaOperacaoService::class)->createPedido($data, auth()->user());
                static::notifySaleCreated($pedido);

                return $pedido;
            });
    }

    protected static function notifySaleCreated(VendaOperacaoPedido $pedido): void
    {
        if ($pedido->isPendenteAprovacao()) {
            Notification::make()
                ->title('Venda enviada para aprovacao')
                ->body('Há desconto comercial ou estoque insuficiente. A venda ficará aguardando aprovação.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Rascunho de venda criado')
            ->body('Revise os dados e confirme a venda para registrar a saída de estoque.')
            ->success()
            ->send();
    }
}
