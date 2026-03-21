<?php

namespace App\Enum;

enum PermissoesEnum: string
{
    case AplicarPermissoes = 'Aplicar Permissoes';
    case EditarNiveisDeAcesso = 'Editar Níveis de Acesso';
    case EditarNivelDeAcessoAdmin = 'Editar Nivel de Acesso: Admin';
}