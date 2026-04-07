<?php

namespace App\Enum;

enum PermissoesEnum: string
{
    // Permissões de sistema
    case AplicarPermissoes          = 'Aplicar Permissoes';
    case EditarNiveisDeAcesso       = 'Editar Níveis de Acesso';
    case EditarNivelDeAcessoAdmin   = 'Editar Nivel de Acesso: Admin';

    // Usuários
    case ListarUsuarios             = 'Listar Usuários';
    case CriarUsuarios              = 'Criar Usuários';
    case EditarUsuarios             = 'Editar Usuários';
    case ExcluirUsuarios            = 'Excluir Usuários';

    // Níveis de Acesso
    case ListarNiveisDeAcesso       = 'Listar Níveis de Acesso';
    case CriarNiveisDeAcesso        = 'Criar Níveis de Acesso';
    case ExcluirNiveisDeAcesso      = 'Excluir Níveis de Acesso';

    // CRM - Produtos
    case ListarProdutosCRM          = 'Listar Produtos CRM';
    case CriarProdutosCRM           = 'Criar Produtos CRM';
    case EditarProdutosCRM          = 'Editar Produtos CRM';
    case ExcluirProdutosCRM         = 'Excluir Produtos CRM';

    // Estoque - Insumos
    case ListarInsumos              = 'Listar Insumos';
    case CriarInsumos               = 'Criar Insumos';
    case EditarInsumos              = 'Editar Insumos';
    case ExcluirInsumos             = 'Excluir Insumos';

    // CRM - Etapas
    case ListarEtapasCRM            = 'Listar Etapas CRM';
    case CriarEtapasCRM             = 'Criar Etapas CRM';
    case EditarEtapasCRM            = 'Editar Etapas CRM';
    case ExcluirEtapasCRM           = 'Excluir Etapas CRM';

    // CRM - Oportunidades
    case ListarOportunidades        = 'Listar Oportunidades';
    case CriarOportunidades         = 'Criar Oportunidades';
    case EditarOportunidades        = 'Editar Oportunidades';
    case ExcluirOportunidades       = 'Excluir Oportunidades';

    // CRM - Produtos da oportunidade
    case ListarProdutosDaOportunidade = 'Listar Produtos da Oportunidade';
    case CriarProdutosDaOportunidade  = 'Criar Produtos da Oportunidade';
    case EditarProdutosDaOportunidade = 'Editar Produtos da Oportunidade';
    case ExcluirProdutosDaOportunidade = 'Excluir Produtos da Oportunidade';

    // CRM - Interações
    case ListarInteracoesDeOportunidade = 'Listar Interações de Oportunidade';
    case CriarInteracoesDeOportunidade  = 'Criar Interações de Oportunidade';
    case EditarInteracoesDeOportunidade = 'Editar Interações de Oportunidade';
    case ExcluirInteracoesDeOportunidade = 'Excluir Interações de Oportunidade';

    // CRM - Tarefas
    case ListarTarefasDeOportunidade = 'Listar Tarefas de Oportunidade';
    case CriarTarefasDeOportunidade  = 'Criar Tarefas de Oportunidade';
    case EditarTarefasDeOportunidade = 'Editar Tarefas de Oportunidade';
    case ExcluirTarefasDeOportunidade = 'Excluir Tarefas de Oportunidade';

    // CRM - Movimentações
    case ListarMovimentacoesDeOportunidade = 'Listar Movimentações de Oportunidade';
    case ExcluirMovimentacoesDeOportunidade = 'Excluir Movimentações de Oportunidade';
}
