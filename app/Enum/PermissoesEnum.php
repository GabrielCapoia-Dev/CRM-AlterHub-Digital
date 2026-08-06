<?php

namespace App\Enum;

enum PermissoesEnum: string
{
    // Permissões de sistema
    case AplicarPermissoes = 'Aplicar Permissoes';
    case EditarNiveisDeAcesso = 'Editar Níveis de Acesso';
    case EditarNivelDeAcessoAdmin = 'Editar Nivel de Acesso: Admin';

    // Usuários
    case ListarUsuarios = 'Listar Usuários';
    case CriarUsuarios = 'Criar Usuários';
    case EditarUsuarios = 'Editar Usuários';
    case ExcluirUsuarios = 'Excluir Usuários';

    // Clientes
    case ListarClientes = 'Listar Clientes';
    case CriarClientes = 'Criar Clientes';
    case EditarClientes = 'Editar Clientes';
    case ExcluirClientes = 'Excluir Clientes';

    // Fornecedores
    case ListarFornecedores = 'Listar Fornecedores';
    case CriarFornecedores = 'Criar Fornecedores';
    case EditarFornecedores = 'Editar Fornecedores';
    case ExcluirFornecedores = 'Excluir Fornecedores';

    // Níveis de Acesso
    case ListarNiveisDeAcesso = 'Listar Níveis de Acesso';
    case CriarNiveisDeAcesso = 'Criar Níveis de Acesso';
    case ExcluirNiveisDeAcesso = 'Excluir Níveis de Acesso';

    // CRM - Produtos
    case ListarProdutosCRM = 'Listar Produtos CRM';
    case CriarProdutosCRM = 'Criar Produtos CRM';
    case EditarProdutosCRM = 'Editar Produtos CRM';
    case ExcluirProdutosCRM = 'Excluir Produtos CRM';

    // Estoque - Insumos
    case ListarInsumos = 'Listar Insumos';
    case CriarInsumos = 'Criar Insumos';
    case EditarInsumos = 'Editar Insumos';
    case ExcluirInsumos = 'Excluir Insumos';
    case MovimentarEstoqueInsumos = 'Movimentar Estoque de Insumos';

    // CRM - Etapas
    case ListarEtapasCRM = 'Listar Etapas CRM';
    case CriarEtapasCRM = 'Criar Etapas CRM';
    case EditarEtapasCRM = 'Editar Etapas CRM';
    case ExcluirEtapasCRM = 'Excluir Etapas CRM';

    // CRM - Oportunidades
    case ListarOportunidades = 'Listar Oportunidades';
    case CriarOportunidades = 'Criar Oportunidades';
    case EditarOportunidades = 'Editar Oportunidades';
    case ExcluirOportunidades = 'Excluir Oportunidades';

    // CRM - Produtos da oportunidade
    case ListarProdutosDaOportunidade = 'Listar Produtos da Oportunidade';
    case CriarProdutosDaOportunidade = 'Criar Produtos da Oportunidade';
    case EditarProdutosDaOportunidade = 'Editar Produtos da Oportunidade';
    case ExcluirProdutosDaOportunidade = 'Excluir Produtos da Oportunidade';
    case AprovarDesconto = 'Aprovar Desconto';

    // CRM - Interações
    case ListarInteracoesDeOportunidade = 'Listar Interações de Oportunidade';
    case CriarInteracoesDeOportunidade = 'Criar Interações de Oportunidade';
    case EditarInteracoesDeOportunidade = 'Editar Interações de Oportunidade';
    case ExcluirInteracoesDeOportunidade = 'Excluir Interações de Oportunidade';

    // CRM - Tarefas
    case ListarTarefasDeOportunidade = 'Listar Tarefas de Oportunidade';
    case CriarTarefasDeOportunidade = 'Criar Tarefas de Oportunidade';
    case EditarTarefasDeOportunidade = 'Editar Tarefas de Oportunidade';
    case ExcluirTarefasDeOportunidade = 'Excluir Tarefas de Oportunidade';

    // CRM - Movimentações
    case ListarMovimentacoesDeOportunidade = 'Listar Movimentações de Oportunidade';
    case ExcluirMovimentacoesDeOportunidade = 'Excluir Movimentações de Oportunidade';

    // Operacao - Despesas
    case ListarDespesasOperacionais = 'Listar Despesas Operacionais';
    case CriarDespesasOperacionais = 'Criar Despesas Operacionais';
    case EditarDespesasOperacionais = 'Editar Despesas Operacionais';
    case ExcluirDespesasOperacionais = 'Excluir Despesas Operacionais';

    // Operacao - Vendas
    case ListarVendasOperacao = 'Listar Vendas de Operacao';
    case CriarVendasOperacao = 'Criar Vendas de Operacao';
    case EditarVendasOperacao = 'Editar Vendas de Operacao';
    case ExcluirVendasOperacao = 'Excluir Vendas de Operacao';
    case VisualizarTodasVendasOperacao = 'Visualizar Todas as Vendas de Operacao';
    case SepararPedidos = 'Separar Pedidos';
    case MovimentarEstoqueProdutos = 'Movimentar Estoque de Produtos';

    // Documentos comerciais e separacao
    case AcessarConfiguracoesDocumentos = 'Acessar Configuracoes de Documentos';
    case EditarConfiguracoesDocumentos = 'Editar Configuracoes de Documentos';
    case GerarPdfPedido = 'Gerar PDF de Pedido';
    case VisualizarAnexosPedido = 'Visualizar Anexos de Pedido';
    case AdicionarFotosPedido = 'Adicionar Fotos de Pedido';
    case RemoverFotosPedido = 'Remover Fotos de Pedido';
    case CadastrarLotesValidades = 'Cadastrar Lotes e Validades';

    // Romaneios de carga
    case GerarRomaneio = 'Gerar Romaneio';
    case ListarRomaneios = 'Listar Romaneios';
    case ReimprimirRomaneio = 'Reimprimir Romaneio';
    case CancelarRomaneio = 'Cancelar Romaneio';

    // Operacao - Analitico
    case ListarVisaoConsolidadaOperacao = 'Listar Visao Consolidada da Operacao';
    case ListarResultadoOperacao = 'Listar Resultado da Operacao';
    case ListarLucroPorProduto = 'Listar Lucro por Produto';
    case ListarDashboardBI = 'Listar Dashboard BI';

    // Producao
    case ListarOrdensProducao = 'Listar Ordens de Producao';
    case CriarOrdensProducao = 'Criar Ordens de Producao';
    case EditarOrdensProducao = 'Editar Ordens de Producao';
    case ExcluirOrdensProducao = 'Excluir Ordens de Producao';

    // Fiscal
    case ListarRegrasTributarias = 'Listar Regras Tributarias';
    case CriarRegrasTributarias = 'Criar Regras Tributarias';
    case EditarRegrasTributarias = 'Editar Regras Tributarias';
    case ExcluirRegrasTributarias = 'Excluir Regras Tributarias';

    // Logistica
    case ListarTransportadoras = 'Listar Transportadoras';
    case CriarTransportadoras = 'Criar Transportadoras';
    case EditarTransportadoras = 'Editar Transportadoras';
    case ExcluirTransportadoras = 'Excluir Transportadoras';

    /**
     * Permissoes mantidas somente para compatibilidade com dados historicos.
     *
     * @return list<string>
     */
    public static function disabledValues(): array
    {
        return [
            self::ListarVisaoConsolidadaOperacao->value,
            self::ListarOrdensProducao->value,
            self::CriarOrdensProducao->value,
            self::EditarOrdensProducao->value,
            self::ExcluirOrdensProducao->value,
            self::ListarRegrasTributarias->value,
            self::CriarRegrasTributarias->value,
            self::EditarRegrasTributarias->value,
            self::ExcluirRegrasTributarias->value,
            self::ListarTransportadoras->value,
            self::CriarTransportadoras->value,
            self::EditarTransportadoras->value,
            self::ExcluirTransportadoras->value,
        ];
    }

    /** @return list<self> */
    public static function activeCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $permission): bool => ! in_array($permission->value, self::disabledValues(), true),
        ));
    }
}
