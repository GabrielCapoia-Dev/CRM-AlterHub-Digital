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
}