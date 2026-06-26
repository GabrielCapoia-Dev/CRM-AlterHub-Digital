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

    // Operacao - Analitico
    case ListarVisaoConsolidadaOperacao = 'Listar Visao Consolidada da Operacao';
    case ListarResultadoOperacao = 'Listar Resultado da Operacao';
    case ListarLucroPorProduto = 'Listar Lucro por Produto';
    case ListarDashboardBI = 'Listar Dashboard BI';
}
